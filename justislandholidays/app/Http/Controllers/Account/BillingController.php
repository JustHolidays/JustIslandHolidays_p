<?php

namespace App\Http\Controllers\Account;

use App\Billing\AccountPlan;
use App\Billing\PayPalClient;
use App\Billing\PayPalException;
use App\Billing\SubscriptionUpdater;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\FeaturedPurchase;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Plan and billing for owners and agents (route gate: use-billing; affiliates and admins are refused).
 * Payment happens on PayPal; owners manage cards and invoices in their PayPal account.
 */
class BillingController extends Controller
{
    public function __construct(private AccountPlan $plans, private PayPalClient $paypal, private SubscriptionUpdater $updater)
    {
    }

    public function show(Request $request): View
    {
        $account = $this->plans->accountFor($request->user());

        return view('account.billing', [
            'currentPlan' => $this->plans->planFor($account),
            'subscription' => $this->plans->currentSubscription($account),
            'plans' => $this->availablePlans($request),
            'paypalReady' => $this->paypal->isConfigured(),
            'isAgency' => $account instanceof Agency,
            'requestable' => $this->requestablePlans($request),
        ]);
    }

    /**
     * Ask for a plan upgrade (20 Nov 2026): emailed to every super admin, reply-to the person
     * asking, and written to the audit log. Nothing changes until an admin acts on it.
     */
    public function requestUpgrade(Request $request): RedirectResponse
    {
        $choices = $this->requestablePlans($request)->pluck('code')->push('other')->all();
        $data = $request->validate([
            'wanted' => ['required', \Illuminate\Validation\Rule::in($choices)],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:wanted,other'],
        ], [
            'wanted.required' => 'Choose the plan you would like.',
            'note.required_if' => 'Tell us what you need.',
        ]);

        $user = $request->user();
        $account = $this->plans->accountFor($user);
        $current = $this->plans->planFor($account)->name;
        $wanted = $data['wanted'] === 'other' ? 'something else' : Plan::where('code', $data['wanted'])->value('name');
        $note = trim((string) ($data['note'] ?? '')) ?: null;

        $admins = \App\Models\User::where('role', \App\Enums\Role::SuperAdmin)
            ->whereNull('deactivated_at')->whereNotNull('email_verified_at')->pluck('email');
        foreach ($admins as $email) {
            \Illuminate\Support\Facades\Mail::to($email)->queue(
                new \App\Mail\PlanUpgradeRequested($user, $current, $wanted, $note, $this->plans->listingsHeldBy($user))
            );
        }
        \App\Support\Audit::record($user, 'plan.upgrade_request', 'user', $user->id, [
            'current' => $current, 'wanted' => $data['wanted'], 'note' => $note !== null, 'sent_to' => $admins->count(),
        ]);

        return redirect()->to(route('account.billing').'#upgrade')->with('upgrade_sent', true);
    }

    /** Plans someone can ask for: active plans for their kind of account, other than Free and their own. */
    private function requestablePlans(Request $request)
    {
        $audience = $request->user()->role?->value === 'agent' ? 'agent' : 'owner';
        $current = $this->plans->planFor($this->plans->accountFor($request->user()))->code;

        return Plan::where('is_active', true)->where('audience', $audience)
            ->whereNotIn('code', ['free', $current])
            ->orderBy('search_boost')->orderBy('id')
            ->get();
    }

    public function checkout(Request $request): RedirectResponse
    {
        // Yearly only (19 Nov 2026). Featured Property is bought for a period instead (featured()).
        $data = $request->validate([
            'plan' => ['required', 'string', Rule::in($this->availablePlans($request)->reject->isSoldByPeriod()->pluck('code'))],
        ]);
        $data['interval'] = 'year';

        $plan = Plan::where('code', $data['plan'])->firstOrFail();
        $paypalPlanId = $data['interval'] === 'year' ? $plan->paypal_plan_id_year : $plan->paypal_plan_id_month;
        if (! $paypalPlanId || ! $this->paypal->isConfigured()) {
            return back()->withErrors(['plan' => 'This plan cannot be bought online yet. Please contact us.']);
        }

        $account = $this->plans->accountFor($request->user());
        $current = $this->plans->currentSubscription($account);

        try {
            // Already paying: ask PayPal to switch the plan on the existing subscription.
            if ($current && $current->status === 'active' && $current->paypal_subscription_id) {
                $approve = $this->paypal->revisePlan($current->paypal_subscription_id, $paypalPlanId, route('account.billing.return', ['subscription_id' => $current->paypal_subscription_id]), route('account.billing.abandoned'));
                Audit::record($request->user(), 'subscription.revise', $current, null, ['plan' => $plan->code, 'interval' => $data['interval']]);

                return $approve ? redirect()->away($approve) : redirect()->route('account.billing')->with('status', 'Your plan change has been sent to PayPal.');
            }

            $subscription = new Subscription();
            $subscription->forceFill([
                'user_id' => $account instanceof Agency ? null : $account->id,
                'agency_id' => $account instanceof Agency ? $account->id : null,
                'plan_id' => $plan->id,
                'billing_interval' => $data['interval'],
                'status' => 'approval_pending',
            ])->save();

            $created = $this->paypal->createSubscription(
                $paypalPlanId,
                (string) $subscription->id,
                route('account.billing.return'),
                route('account.billing.abandoned'),
            );

            $subscription->forceFill(['paypal_subscription_id' => $created['id']])->save();
            Audit::record($request->user(), 'subscription.start', $subscription, null, ['plan' => $plan->code, 'interval' => $data['interval']]);
        } catch (PayPalException $e) {
            Log::warning('PayPal checkout failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['plan' => 'We could not reach PayPal. Please try again in a few minutes.']);
        }

        abort_unless($created['approve_url'], 502);

        return redirect()->away($created['approve_url']);
    }

    /** PayPal sends the payer back here after approving. The webhook confirms it as well. */
    public function returned(Request $request): RedirectResponse
    {
        $paypalId = (string) $request->query('subscription_id');
        $account = $this->plans->accountFor($request->user());

        $subscription = Subscription::where('paypal_subscription_id', $paypalId)
            ->where($account instanceof Agency ? 'agency_id' : 'user_id', $account->id)
            ->first();

        if (! $subscription) {
            return redirect()->route('account.billing')->withErrors(['plan' => 'We could not find that subscription.']);
        }

        try {
            $this->updater->apply($subscription, $this->paypal->getSubscription($paypalId));
        } catch (PayPalException $e) {
            Log::warning('PayPal return lookup failed', ['error' => $e->getMessage()]);

            return redirect()->route('account.billing')->with('status', 'Thank you. PayPal is still confirming your payment; your plan will update shortly.');
        }

        return redirect()->route('account.billing')->with('status', $subscription->status === 'active'
            ? 'Thank you. Your '.$subscription->plan->name.' plan is now active.'
            : 'Thank you. PayPal is confirming your payment; your plan will update shortly.');
    }

    public function abandoned(): RedirectResponse
    {
        return redirect()->route('account.billing')->with('status', 'You did not finish at PayPal, so nothing has changed.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $account = $this->plans->accountFor($request->user());
        $subscription = $this->plans->currentSubscription($account);

        if (! $subscription || $subscription->status !== 'active' || ! $subscription->paypal_subscription_id) {
            return back()->withErrors(['plan' => 'You have no paid plan to cancel.']);
        }

        try {
            $this->paypal->cancelSubscription($subscription->paypal_subscription_id, 'Cancelled by the customer');
        } catch (PayPalException $e) {
            Log::warning('PayPal cancel failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['plan' => 'We could not reach PayPal. Please try again, or cancel in your PayPal account.']);
        }

        $this->updater->apply($subscription, ['id' => $subscription->paypal_subscription_id, 'status' => 'CANCELLED']);
        Audit::record($request->user(), 'subscription.cancel', $subscription);

        $until = $subscription->fresh()->ends_at;

        return redirect()->route('account.billing')->with('status', $until && $until->isFuture()
            ? 'Your plan is cancelled. It stays active until '.$until->format('j F Y').', then your listings move to the Free Plan.'
            : 'Your plan is cancelled. Your listings are now on the Free Plan.');
    }

    /**
     * Featured Property: a one-off PayPal payment for 3, 6, 9 or 12 months from the day they
     * pay. No renewal; they can buy again. The period is recorded as a subscription with
     * billing_interval 'one_off' that ends on its end date.
     */
    public function featured(Request $request): RedirectResponse
    {
        $plan = $this->availablePlans($request)->first->isSoldByPeriod();
        abort_unless($plan, 404);

        $prices = $plan->periodPrices();
        $months = (int) $request->validate(['months' => ['required', 'integer', Rule::in(array_keys($prices))]], [
            'months.in' => 'Choose 3, 6, 9 or 12 months.',
        ])['months'];
        if (! $this->paypal->isConfigured()) {
            return back()->withErrors(['months' => 'Online payment is not set up yet. Please contact us.']);
        }

        $account = $this->plans->accountFor($request->user());
        $purchase = FeaturedPurchase::create([
            'user_id' => $account instanceof Agency ? null : $account->id,
            'agency_id' => $account instanceof Agency ? $account->id : null,
            'plan_id' => $plan->id,
            'months' => $months,
            'amount_cents' => $prices[$months],
            'currency' => $plan->currency,
        ]);

        try {
            $order = $this->paypal->createOrder(
                $prices[$months], $plan->currency, 'featured-'.$purchase->id,
                "{$plan->name}, {$months} months",
                route('account.billing.featured.return'), route('account.billing.abandoned'),
            );
        } catch (PayPalException $e) {
            Log::warning('PayPal featured order failed', ['error' => $e->getMessage()]);
            $purchase->update(['status' => 'failed']);

            return back()->withErrors(['months' => 'We could not reach PayPal. Please try again in a few minutes.']);
        }

        $purchase->update(['paypal_order_id' => $order['id']]);
        Audit::record($request->user(), 'featured.start', 'featured_purchase', $purchase->id, ['months' => $months, 'amount_cents' => $prices[$months]]);
        abort_unless($order['approve_url'], 502);

        return redirect()->away($order['approve_url']);
    }

    /** PayPal sends the payer back with ?token=ORDER_ID: take the payment and start the period. */
    public function featuredReturned(Request $request): RedirectResponse
    {
        $account = $this->plans->accountFor($request->user());
        $purchase = FeaturedPurchase::where('paypal_order_id', (string) $request->query('token'))
            ->where($account instanceof Agency ? 'agency_id' : 'user_id', $account->id)
            ->with('plan')
            ->first();

        if (! $purchase) {
            return redirect()->route('account.billing')->withErrors(['months' => 'We could not find that payment.']);
        }
        if ($purchase->status === 'completed') {
            return redirect()->route('account.billing')->with('status', 'Your Featured Plan period is already active.');
        }

        try {
            $status = $this->paypal->captureOrder($purchase->paypal_order_id);
        } catch (PayPalException $e) {
            Log::warning('PayPal featured capture failed', ['error' => $e->getMessage()]);

            return redirect()->route('account.billing')->withErrors(['months' => 'PayPal could not take the payment, so nothing has changed. Please try again.']);
        }

        if ($status !== 'COMPLETED') {
            $purchase->update(['status' => 'failed']);

            return redirect()->route('account.billing')->withErrors(['months' => 'PayPal did not complete the payment, so nothing has changed.']);
        }

        DB::transaction(function () use ($purchase, $account, $request) {
            $subscription = new Subscription();
            $subscription->forceFill([
                'user_id' => $purchase->user_id,
                'agency_id' => $purchase->agency_id,
                'plan_id' => $purchase->plan_id,
                'billing_interval' => 'one_off',
                'status' => 'active',
                'ends_at' => now()->addMonthsNoOverflow($purchase->months),
            ])->save();
            $purchase->update(['status' => 'completed', 'subscription_id' => $subscription->id]);
            $this->plans->sync($account);
            Audit::record($request->user(), 'featured.paid', 'featured_purchase', $purchase->id, ['months' => $purchase->months, 'ends_at' => $subscription->ends_at->toDateString()]);
        });

        return redirect()->route('account.billing')->with('status', 'Thank you. '.$purchase->plan->name.' is active until '.now()->addMonthsNoOverflow($purchase->months)->format('j F Y').'.');
    }

    /** Active plans for the user's audience that can be bought: a yearly price, or Featured Property period prices. */
    private function availablePlans(Request $request)
    {
        $audience = $request->user()->role?->value === 'agent' ? 'agent' : 'owner';

        return Plan::where('is_active', true)
            ->where('audience', $audience)
            ->orderBy('search_boost')
            ->get()
            ->filter(fn (Plan $p) => $p->isSoldByPeriod() ? $p->periodPrices() !== [] : $p->price_year_cents !== null)
            ->values();
    }
}
