<?php

namespace App\Http\Controllers\Admin;

use App\Billing\AccountPlan;
use App\Enums\Role;
use App\Listings\RankScore;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Plans and subscriptions (super admins). Prices and limits live here, never in code.
 * Affiliates never appear: they are outside billing.
 *
 * "Grant a plan" gives an owner or agency a complimentary plan until a date (for example
 * free Featured placement for early owners). It is account-wide: an agency's grant covers
 * all its listings.
 */
class PlanController extends AdminController
{
    public function index(Request $request): View
    {
        $subs = Subscription::with(['plan', 'user', 'agency'])->latest('id');
        if (in_array($status = $request->query('status'), ['approval_pending', 'approved', 'active', 'suspended', 'cancelled', 'expired'], true)) {
            $subs->where('status', $status);
        }

        return view('admin.plans.index', [
            'plans' => Plan::orderBy('audience')->orderBy('search_boost')->get(),
            'subscriptions' => $subs->paginate(self::PER_PAGE)->withQueryString(),
            'paypalMode' => config('services.paypal.mode'),
        ]);
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.edit', ['plan' => $plan]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'max_listings' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'search_boost' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_featured' => ['nullable', 'boolean'],
            'show_direct_contact' => ['nullable', 'boolean'],
            'currency' => ['required', Rule::in(['EUR', 'GBP'])],
            'price_month' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'price_year' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'paypal_plan_id_month' => ['nullable', 'regex:/^P-[A-Z0-9]{10,40}$/'],
            'paypal_plan_id_year' => ['nullable', 'regex:/^P-[A-Z0-9]{10,40}$/'],
            'is_active' => ['nullable', 'boolean'],
        ], ['paypal_plan_id_month.regex' => 'PayPal plan ids look like P-1AB23456CD789012E.', 'paypal_plan_id_year.regex' => 'PayPal plan ids look like P-1AB23456CD789012E.']);

        $before = $plan->toArray();
        $plan->fill([
            'name' => $data['name'],
            'max_listings' => $data['max_listings'] ?? null,
            'search_boost' => $data['search_boost'],
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'show_direct_contact' => (bool) ($data['show_direct_contact'] ?? false),
            'currency' => $data['currency'],
            'price_month_cents' => isset($data['price_month']) ? (int) round($data['price_month'] * 100) : null,
            'price_year_cents' => isset($data['price_year']) ? (int) round($data['price_year'] * 100) : null,
            'paypal_plan_id_month' => $data['paypal_plan_id_month'] ?? null,
            'paypal_plan_id_year' => $data['paypal_plan_id_year'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ])->save();
        RankScore::flush();

        Audit::record($request->user(), 'plan.update', $plan, null, ['before' => $before, 'after' => $plan->fresh()->toArray()]);

        return redirect()->route('admin.plans.index')->with('status', "{$plan->name} saved. Ranking changes apply to listings at the next nightly update.");
    }

    /** A complimentary plan until a date, for one owner or agent's account. Goes through AccountPlan and is audited. */
    public function grant(Request $request, AccountPlan $plans): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'plan' => ['required', Rule::exists('plans', 'code')->where('is_active', true)],
            'until' => ['required', 'date', 'after:today', 'before:+3 years'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $user = User::where('email', strtolower($data['email']))->whereIn('role', [Role::Owner, Role::Agent])->first();
        if (! $user) {
            throw ValidationException::withMessages(['email' => 'No owner or agent has that email address.']);
        }

        $plan = Plan::where('code', $data['plan'])->firstOrFail();
        $account = $plans->accountFor($user);
        if ($plan->audience !== ($user->role === Role::Agent ? 'agent' : 'owner') && $plan->code !== 'free') {
            throw ValidationException::withMessages(['plan' => "The {$plan->name} plan is not for this kind of account."]);
        }

        DB::transaction(function () use ($account, $plan, $data, $request, $plans) {
            $sub = new Subscription();
            $sub->forceFill([
                'user_id' => $account instanceof User ? $account->id : null,
                'agency_id' => $account instanceof User ? null : $account->id,
                'plan_id' => $plan->id,
                'billing_interval' => 'year',
                'status' => 'active',
                'ends_at' => Carbon::parse($data['until'])->endOfDay(),
            ])->save();

            $plans->sync($account);
            Audit::record($request->user(), 'plan.override', $sub, null, ['plan' => $plan->code, 'until' => $data['until'], 'reason' => $data['reason'], 'account' => $account->getTable().':'.$account->id]);
        });

        return back()->with('status', "{$plan->name} granted until ".Carbon::parse($data['until'])->format('j F Y').'.');
    }

    public function subscription(Subscription $subscription): View
    {
        return view('admin.plans.subscription', ['subscription' => $subscription->load(['plan', 'user', 'agency', 'payments'])]);
    }

    /** Refunds are made in PayPal and recorded here. */
    public function markRefunded(Request $request, SubscriptionPayment $payment): RedirectResponse
    {
        $note = $this->reason($request);
        $payment->forceFill(['status' => 'refunded'])->save();
        Audit::record($request->user(), 'payment.refunded', $payment, null, ['note' => $note, 'paypal_transaction_id' => $payment->paypal_transaction_id]);

        return back()->with('status', 'Payment recorded as refunded.');
    }
}
