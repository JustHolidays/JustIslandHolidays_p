<?php

namespace App\Http\Controllers;

use App\Billing\PayPalClient;
use App\Billing\SubscriptionUpdater;
use App\Models\PaypalEvent;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POST /api/webhooks/paypal. Verifies the signature with PayPal, then handles each event once:
 * the event id goes into paypal_events in the same transaction as the changes, so a retry
 * after a failure is processed again, and a duplicate delivery is skipped.
 */
class PayPalWebhookController extends Controller
{
    public function __construct(private PayPalClient $paypal, private SubscriptionUpdater $updater)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $event = json_decode($request->getContent(), true);
        if (! is_array($event) || ! isset($event['id'], $event['event_type'])) {
            return response()->json(['error' => 'Bad request'], 400);
        }

        if (! $this->paypal->verifyWebhook($request->headers->all(), $event)) {
            Log::warning('PayPal webhook signature failed', ['event_id' => $event['id']]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if (PaypalEvent::whereKey($event['id'])->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        try {
            DB::transaction(function () use ($event) {
                PaypalEvent::create(['id' => $event['id'], 'event_type' => $event['event_type'], 'received_at' => now()]);
                $this->handle($event['event_type'], $event['resource'] ?? []);
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'duplicate']);   // a parallel delivery won the race
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Not processed'], 500);   // PayPal will retry
        }

        return response()->json(['status' => 'ok']);
    }

    private function handle(string $type, array $resource): void
    {
        match (true) {
            str_starts_with($type, 'BILLING.SUBSCRIPTION.') && $type !== 'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => $this->subscriptionChanged($resource),
            $type === 'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => $this->paymentFailed($resource),
            $type === 'PAYMENT.SALE.COMPLETED' => $this->saleCompleted($resource),
            in_array($type, ['PAYMENT.SALE.REFUNDED', 'PAYMENT.SALE.REVERSED'], true) => $this->saleRefunded($resource),
            default => null,   // recorded in paypal_events, otherwise ignored
        };
    }

    private function subscriptionChanged(array $resource): void
    {
        if ($subscription = $this->updater->find($resource)) {
            $this->updater->apply($subscription, $resource);
        } else {
            Log::notice('PayPal webhook for an unknown subscription', ['paypal_id' => $resource['id'] ?? null]);
        }
    }

    /** PayPal retries failed payments itself and suspends the subscription if they keep failing. */
    private function paymentFailed(array $resource): void
    {
        $subscription = $this->updater->find($resource);
        Audit::record(null, 'subscription.payment_failed', $subscription ?? 'subscription', $subscription?->id ?? ($resource['id'] ?? null));
    }

    private function saleCompleted(array $resource): void
    {
        $subscription = Subscription::where('paypal_subscription_id', $resource['billing_agreement_id'] ?? '')->first();
        if (! $subscription || empty($resource['id'])) {
            return;
        }

        $amount = $resource['amount']['total'] ?? null;

        SubscriptionPayment::updateOrCreate(
            ['paypal_transaction_id' => $resource['id']],
            [
                'subscription_id' => $subscription->id,
                'amount_cents' => $amount === null ? 0 : (int) round(((float) $amount) * 100),
                'currency' => $resource['amount']['currency'] ?? 'EUR',
                'status' => 'completed',
                'paid_at' => isset($resource['create_time']) ? Carbon::parse($resource['create_time']) : now(),
            ],
        );
    }

    private function saleRefunded(array $resource): void
    {
        $saleId = $resource['sale_id'] ?? $resource['id'] ?? null;
        if ($saleId) {
            SubscriptionPayment::where('paypal_transaction_id', $saleId)->update(['status' => 'refunded']);
        }
    }
}
