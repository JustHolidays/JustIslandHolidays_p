<?php

namespace App\Http\Controllers\Account;

use App\Billing\AccountPlan;
use App\Billing\PayPalClient;
use App\Billing\PayPalException;
use App\Billing\SubscriptionUpdater;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', Rule::in($this->availablePlans($request)->pluck('code'))],
            'interval' => ['required', Rule::in(['month', 'year'])],
        ]);

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
            ? 'Your plan is cancelled. It stays active until '.$until->format('j F Y').', then your listings move to the Free plan.'
            : 'Your plan is cancelled. Your listings are now on the Free plan.');
    }

    /** Active plans for the user's audience that have a price. */
    private function availablePlans(Request $request)
    {
        $audience = $request->user()->role?->value === 'agent' ? 'agent' : 'owner';

        return Plan::where('is_active', true)
            ->where('audience', $audience)
            ->where(fn ($q) => $q->whereNotNull('price_month_cents')->orWhereNotNull('price_year_cents'))
            ->orderBy('search_boost')
            ->get();
    }
}
