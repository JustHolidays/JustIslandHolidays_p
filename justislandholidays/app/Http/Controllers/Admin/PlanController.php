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
 * "Gift A Plan" gives an owner or agency a complimentary plan until a date (for example
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
        // Yearly only (19 Nov 2026): monthly prices are no longer edited or offered; any
        // stored monthly values are left alone so existing monthly subscriptions keep running.
        $rules = [
            'name' => ['required', 'string', 'max:80'],
            'max_listings' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'max_photos' => ['required', 'integer', 'min:1', 'max:50'],
            'search_boost' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_featured' => ['nullable', 'boolean'],
            'show_direct_contact' => ['nullable', 'boolean'],
            'has_booking_calendar' => ['nullable', 'boolean'],
            'currency' => ['required', Rule::in(['EUR', 'GBP'])],
            'price_year' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'paypal_plan_id_year' => ['nullable', 'regex:/^P-[A-Z0-9]{10,40}$/'],
            'is_active' => ['nullable', 'boolean'],
        ];
        foreach (Plan::FEATURED_PERIODS as $months) {
            $rules["price_{$months}m"] = ['nullable', 'numeric', 'min:0', 'max:100000'];
        }
        $data = $request->validate($rules, ['paypal_plan_id_year.regex' => 'PayPal plan ids look like P-1AB23456CD789012E.']);

        $cents = fn ($v) => isset($v) && $v !== '' ? (int) round($v * 100) : null;
        $before = $plan->toArray();
        $attributes = [
            'name' => $data['name'],
            'max_listings' => $data['max_listings'] ?? null,
            'max_photos' => (int) $data['max_photos'],
            'search_boost' => $data['search_boost'],
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'show_direct_contact' => (bool) ($data['show_direct_contact'] ?? false),
            'has_booking_calendar' => (bool) ($data['has_booking_calendar'] ?? false),
            'currency' => $data['currency'],
            'price_year_cents' => $cents($data['price_year'] ?? null),
            'paypal_plan_id_year' => $data['paypal_plan_id_year'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
        foreach (Plan::FEATURED_PERIODS as $months) {
            $attributes["price_{$months}m_cents"] = $cents($data["price_{$months}m"] ?? null);
        }
        $plan->fill($attributes)->save();
        RankScore::flush();

        Audit::record($request->user(), 'plan.update', $plan, null, ['before' => $before, 'after' => $plan->fresh()->toArray()]);

        return redirect()->route('admin.plans.index')->with('status', "{$plan->name} saved. Ranking changes apply to listings at the next nightly update.");
    }

    /** Header image for the plan's card on the List your holiday home page. Audited. */
    public function updateHeader(Request $request, Plan $plan): RedirectResponse
    {
        $before = $plan->only(['header_image_path', 'header_image_alt']);

        if ($request->boolean('remove')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete((string) $plan->header_image_path);
            $plan->forceFill(['header_image_path' => null, 'header_image_alt' => null])->save();
            Audit::record($request->user(), 'plan.header_remove', $plan, null, ['before' => $before]);

            return back()->with('status', 'Card image removed. The card shows the placeholder.');
        }

        $request->validate([
            'header_image' => [$plan->header_image_path ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=800,min_height=300'],
            'header_image_alt' => ['required', 'string', 'max:160'],
        ], [
            'header_image.required' => 'Choose an image.',
            'header_image.dimensions' => 'The image must be at least 800 × 300 pixels.',
            'header_image_alt.required' => 'Describe the image for people who cannot see it.',
        ]);

        $path = $plan->header_image_path;
        if ($request->hasFile('header_image')) {
            $path = \App\Support\PlanImage::store($request->file('header_image'));
            \Illuminate\Support\Facades\Storage::disk('public')->delete((string) $plan->header_image_path);
        }
        $plan->forceFill(['header_image_path' => $path, 'header_image_alt' => trim((string) $request->input('header_image_alt'))])->save();
        Audit::record($request->user(), 'plan.header_update', $plan, null, ['before' => $before, 'after' => $plan->only(['header_image_path', 'header_image_alt'])]);

        return back()->with('status', 'Card image saved.');
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

        return back()->with('status', "{$plan->name} gifted until ".Carbon::parse($data['until'])->format('j F Y').'.');
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
