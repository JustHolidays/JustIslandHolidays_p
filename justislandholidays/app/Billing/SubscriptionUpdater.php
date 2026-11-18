<?php

namespace App\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Audit;
use Illuminate\Support\Carbon;

/**
 * Copies PayPal's view of a subscription onto our row, then re-applies the account's
 * plan to its listings. Used by the return page and the webhook.
 */
final class SubscriptionUpdater
{
    private const STATUS = [
        'APPROVAL_PENDING' => 'approval_pending',
        'APPROVED' => 'approved',
        'ACTIVE' => 'active',
        'SUSPENDED' => 'suspended',
        'CANCELLED' => 'cancelled',
        'EXPIRED' => 'expired',
    ];

    public function __construct(private AccountPlan $plans)
    {
    }

    /** Our row for a PayPal subscription resource: by PayPal id, then by our custom_id. */
    public function find(array $resource): ?Subscription
    {
        $id = $resource['id'] ?? null;

        return ($id ? Subscription::where('paypal_subscription_id', $id)->first() : null)
            ?? (ctype_digit((string) ($resource['custom_id'] ?? '')) ? Subscription::find((int) $resource['custom_id']) : null);
    }

    /** @param array<string, mixed> $resource a PayPal subscription object */
    public function apply(Subscription $subscription, array $resource): void
    {
        $before = $subscription->only(['status', 'plan_id', 'next_billing_at', 'ends_at']);

        $status = self::STATUS[$resource['status'] ?? ''] ?? $subscription->status;
        $nextBilling = isset($resource['billing_info']['next_billing_time'])
            ? Carbon::parse($resource['billing_info']['next_billing_time'])
            : $subscription->next_billing_at;

        if ($plan = $this->planFor($resource['plan_id'] ?? null)) {
            $subscription->plan_id = $plan->id;
            $subscription->billing_interval = $plan->paypal_plan_id_year === $resource['plan_id'] ? 'year' : 'month';
        }

        $subscription->paypal_subscription_id ??= $resource['id'] ?? null;
        $subscription->paypal_payer_id = $resource['subscriber']['payer_id'] ?? $subscription->paypal_payer_id;

        if (in_array($status, ['cancelled', 'suspended', 'expired'], true)) {
            // Access runs to the end of the period already paid for.
            $paidUntil = $subscription->next_billing_at ?? $nextBilling;
            $subscription->ends_at ??= ($paidUntil && $paidUntil->isFuture()) ? $paidUntil : now();
            if ($status === 'cancelled') {
                $subscription->cancelled_at ??= now();
            }
            if ($status === 'expired') {
                $subscription->ends_at = now();
            }
        } else {
            $subscription->next_billing_at = $nextBilling;
            if ($status === 'active') {
                $subscription->ends_at = null;
                $subscription->cancelled_at = null;
            }
        }

        $subscription->status = $status;
        $subscription->save();

        Audit::record(null, 'subscription.update', $subscription, null, [
            'before' => $before,
            'after' => $subscription->only(['status', 'plan_id', 'next_billing_at', 'ends_at']),
        ]);

        $account = $subscription->agency_id ? $subscription->agency : $subscription->user;
        if ($account) {
            $this->plans->sync($account);
        }
    }

    private function planFor(?string $paypalPlanId): ?Plan
    {
        if (! $paypalPlanId) {
            return null;
        }

        return Plan::where('paypal_plan_id_month', $paypalPlanId)->orWhere('paypal_plan_id_year', $paypalPlanId)->first();
    }
}
