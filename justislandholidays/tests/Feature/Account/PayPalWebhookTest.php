<?php

namespace Tests\Feature\Account;

use App\Enums\ListingStatus as S;
use App\Models\PaypalEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

class PayPalWebhookTest extends TestCase
{
    use BuildsListings, RefreshDatabase;

    private const API = 'https://api-m.sandbox.paypal.com';

    private User $owner;

    private Subscription $sub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        config(['services.paypal' => ['mode' => 'sandbox', 'client_id' => 'c', 'client_secret' => 's', 'webhook_id' => 'WH-TEST', 'brand_name' => 'JIH']]);

        $this->owner = User::factory()->owner()->create();
        $this->sub = new Subscription();
        $this->sub->forceFill(['user_id' => $this->owner->id, 'plan_id' => Plan::where('code', 'standard')->value('id'),
            'billing_interval' => 'year', 'status' => 'approval_pending', 'paypal_subscription_id' => 'I-ABC'])->save();
    }

    private function fakeVerification(string $result = 'SUCCESS'): void
    {
        Http::fake([
            self::API.'/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            self::API.'/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => $result]),
        ]);
    }

    private function deliver(string $type, array $resource, string $id = 'WH-EVT-1'): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', '/api/webhooks/paypal', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYPAL_TRANSMISSION_ID' => 'tx-1',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601String(),
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'sig',
            'HTTP_PAYPAL_CERT_URL' => 'https://api.paypal.com/cert',
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
        ], json_encode(['id' => $id, 'event_type' => $type, 'resource' => $resource]));
    }

    private function activated(array $extra = []): array
    {
        return $extra + [
            'id' => 'I-ABC', 'status' => 'ACTIVE', 'plan_id' => 'P-FEAT-YEAR', 'custom_id' => (string) $this->sub->id,
            'billing_info' => ['next_billing_time' => now()->addYear()->toIso8601String()],
        ];
    }

    public function test_bad_signature_is_refused_and_nothing_changes(): void
    {
        $this->fakeVerification('FAILURE');

        $this->deliver('BILLING.SUBSCRIPTION.ACTIVATED', $this->activated())->assertStatus(400);

        $this->assertSame('approval_pending', $this->sub->fresh()->status);
        $this->assertSame(0, PaypalEvent::count());
    }

    public function test_signature_is_checked_with_paypal_using_our_webhook_id(): void
    {
        $this->fakeVerification();

        $this->deliver('BILLING.SUBSCRIPTION.ACTIVATED', $this->activated())->assertOk();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/verify-webhook-signature')
            && $r['webhook_id'] === 'WH-TEST'
            && $r['transmission_sig'] === 'sig'
            && $r['webhook_event']['id'] === 'WH-EVT-1');
    }

    public function test_activation_sets_the_plan_from_paypal_and_features_listings(): void
    {
        $this->fakeVerification();
        $listing = $this->setStatus($this->completeListing($this->owner), S::Live);

        $this->deliver('BILLING.SUBSCRIPTION.ACTIVATED', $this->activated())->assertOk()->assertJson(['status' => 'ok']);

        $sub = $this->sub->fresh();
        $this->assertSame('active', $sub->status);
        $this->assertSame('featured', $sub->plan->code, 'The plan comes from PayPal\'s plan id.');
        $this->assertSame('featured', $listing->fresh()->plan_code);
        $this->assertNotNull($listing->fresh()->featured_until);
    }

    public function test_duplicate_delivery_is_processed_once(): void
    {
        $this->fakeVerification();

        $this->deliver('BILLING.SUBSCRIPTION.ACTIVATED', $this->activated())->assertOk();
        $this->sub->forceFill(['status' => 'suspended'])->save();   // would be undone if processed again
        $this->deliver('BILLING.SUBSCRIPTION.ACTIVATED', $this->activated())->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertSame('suspended', $this->sub->fresh()->status);
        $this->assertSame(1, PaypalEvent::count());
    }

    public function test_cancellation_keeps_access_until_the_paid_period_ends(): void
    {
        $this->fakeVerification();
        $this->deliver('BILLING.SUBSCRIPTION.ACTIVATED', $this->activated(), 'E1');
        $paidUntil = $this->sub->fresh()->next_billing_at;

        $this->deliver('BILLING.SUBSCRIPTION.CANCELLED', ['id' => 'I-ABC', 'status' => 'CANCELLED'], 'E2')->assertOk();

        $sub = $this->sub->fresh();
        $this->assertSame('cancelled', $sub->status);
        $this->assertTrue($sub->ends_at->equalTo($paidUntil));
    }

    public function test_completed_payment_is_recorded_once(): void
    {
        $this->fakeVerification();
        $sale = ['id' => 'SALE-1', 'billing_agreement_id' => 'I-ABC', 'amount' => ['total' => '120.00', 'currency' => 'EUR'], 'create_time' => now()->toIso8601String()];

        $this->deliver('PAYMENT.SALE.COMPLETED', $sale, 'E1')->assertOk();
        $this->deliver('PAYMENT.SALE.COMPLETED', $sale, 'E2')->assertOk();   // a different event about the same sale

        $payment = SubscriptionPayment::sole();
        $this->assertSame(12000, $payment->amount_cents);
        $this->assertSame('EUR', $payment->currency);
        $this->assertSame('completed', $payment->status);
    }

    public function test_refund_marks_the_payment(): void
    {
        $this->fakeVerification();
        $this->deliver('PAYMENT.SALE.COMPLETED', ['id' => 'SALE-1', 'billing_agreement_id' => 'I-ABC', 'amount' => ['total' => '120.00', 'currency' => 'EUR']], 'E1');

        $this->deliver('PAYMENT.SALE.REFUNDED', ['id' => 'REFUND-1', 'sale_id' => 'SALE-1'], 'E2')->assertOk();

        $this->assertSame('refunded', SubscriptionPayment::sole()->status);
    }

    public function test_unknown_events_and_subscriptions_are_accepted_and_logged(): void
    {
        $this->fakeVerification();

        $this->deliver('CUSTOMER.DISPUTE.CREATED', ['id' => 'D-1'], 'E1')->assertOk();
        $this->deliver('BILLING.SUBSCRIPTION.ACTIVATED', ['id' => 'I-UNKNOWN', 'status' => 'ACTIVE'], 'E2')->assertOk();

        $this->assertSame(2, PaypalEvent::count());
    }

    public function test_malformed_body_is_refused(): void
    {
        $this->call('POST', '/api/webhooks/paypal', [], [], [], ['CONTENT_TYPE' => 'application/json'], 'not json')->assertStatus(400);
    }

    public function test_webhook_needs_no_csrf_token_or_session(): void
    {
        $this->fakeVerification();

        $response = $this->deliver('BILLING.SUBSCRIPTION.ACTIVATED', $this->activated());

        $response->assertOk();
        $this->assertSame([], $response->headers->getCookies());
    }
}
