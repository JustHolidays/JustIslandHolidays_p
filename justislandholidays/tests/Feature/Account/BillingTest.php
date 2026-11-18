<?php

namespace Tests\Feature\Account;

use App\Enums\ListingStatus as S;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use BuildsListings, RefreshDatabase;

    private const API = 'https://api-m.sandbox.paypal.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        config(['services.paypal' => [
            'mode' => 'sandbox', 'client_id' => 'test-client', 'client_secret' => 'test-secret',
            'webhook_id' => 'WH-TEST', 'brand_name' => 'Just Island Holidays',
        ]]);
    }

    private function fakePayPal(array $extra = []): void
    {
        Http::fake($extra + [
            self::API.'/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            self::API.'/v1/billing/subscriptions' => Http::response([
                'id' => 'I-NEW123', 'status' => 'APPROVAL_PENDING',
                'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/webapps/billing/subscriptions?ba_token=BA-1']],
            ], 201),
            self::API.'/v1/billing/subscriptions/I-NEW123' => Http::response([
                'id' => 'I-NEW123', 'status' => 'ACTIVE', 'plan_id' => 'P-STD-YEAR',
                'billing_info' => ['next_billing_time' => now()->addYear()->toIso8601String()],
                'subscriber' => ['payer_id' => 'PAYER1'],
            ]),
            self::API.'/v1/billing/subscriptions/I-NEW123/cancel' => Http::response(null, 204),
        ]);
    }

    public function test_affiliates_and_admins_are_refused_billing(): void
    {
        foreach ([User::factory()->affiliate()->create(), $this->superAdmin()] as $user) {
            $this->actingAs($user)->get('/account/billing')->assertForbidden();
            $this->actingAs($user)->post('/account/billing/checkout', ['plan' => 'standard', 'interval' => 'year'])->assertForbidden();
            $this->actingAs($user)->post('/account/billing/cancel')->assertForbidden();
        }
    }

    public function test_billing_page_shows_plans_for_the_audience(): void
    {
        $this->actingAs(User::factory()->owner()->create())->get('/account/billing')->assertOk()
            ->assertSee('Your plan: Free')
            ->assertSee('Standard')
            ->assertSee('€120 a year')
            ->assertDontSee('Agent Plus');

        $this->actingAs(User::factory()->agent()->create())->get('/account/billing')->assertOk()
            ->assertSee('Agent Plus')
            ->assertDontSee('Featured on the home');
    }

    public function test_checkout_creates_a_pending_subscription_and_sends_the_owner_to_paypal(): void
    {
        $this->fakePayPal();
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/account/billing/checkout', ['plan' => 'standard', 'interval' => 'year'])
            ->assertRedirect('https://www.sandbox.paypal.com/webapps/billing/subscriptions?ba_token=BA-1');

        $sub = Subscription::sole();
        $this->assertSame($owner->id, $sub->user_id);
        $this->assertSame('approval_pending', $sub->status);
        $this->assertSame('I-NEW123', $sub->paypal_subscription_id);

        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/v1/billing/subscriptions'
            && $r['plan_id'] === 'P-STD-YEAR'
            && $r['custom_id'] === (string) $sub->id
            && $r->hasHeader('PayPal-Request-Id'));
    }

    public function test_owner_cannot_buy_an_agent_plan_or_a_plan_without_a_price(): void
    {
        $this->fakePayPal();
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/account/billing/checkout', ['plan' => 'agent_plus', 'interval' => 'year'])->assertSessionHasErrors('plan');
        $this->actingAs($owner)->post('/account/billing/checkout', ['plan' => 'free', 'interval' => 'year'])->assertSessionHasErrors('plan');
        $this->actingAs($owner)->post('/account/billing/checkout', ['plan' => 'featured', 'interval' => 'month'])->assertSessionHasErrors('plan');
        $this->assertSame(0, Subscription::count());
    }

    public function test_paypal_not_set_up_shows_a_friendly_message(): void
    {
        config(['services.paypal.client_id' => null]);

        $this->actingAs(User::factory()->owner()->create())->get('/account/billing')->assertSee('Online payment is not set up yet');
        $this->actingAs(User::factory()->owner()->create())->post('/account/billing/checkout', ['plan' => 'standard', 'interval' => 'year'])
            ->assertSessionHasErrors('plan');
    }

    public function test_return_from_paypal_activates_the_plan_and_updates_listings(): void
    {
        $this->fakePayPal();
        $owner = User::factory()->owner()->create();
        $listing = $this->setStatus($this->completeListing($owner), S::Live);
        $this->actingAs($owner)->post('/account/billing/checkout', ['plan' => 'standard', 'interval' => 'year']);

        $this->actingAs($owner)->get('/account/billing/return?subscription_id=I-NEW123')
            ->assertRedirect('/account/billing')
            ->assertSessionHas('status', 'Thank you. Your Standard plan is now active.');

        $sub = Subscription::sole();
        $this->assertSame('active', $sub->status);
        $this->assertSame('PAYER1', $sub->paypal_payer_id);
        $this->assertSame('standard', $listing->fresh()->plan_code);
    }

    public function test_return_for_someone_elses_subscription_is_refused(): void
    {
        $this->fakePayPal();
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner)->post('/account/billing/checkout', ['plan' => 'standard', 'interval' => 'year']);

        $this->actingAs(User::factory()->owner()->create())->get('/account/billing/return?subscription_id=I-NEW123')
            ->assertSessionHasErrors('plan');
        $this->assertSame('approval_pending', Subscription::sole()->status);
    }

    public function test_cancel_keeps_the_plan_until_the_paid_period_ends(): void
    {
        $this->fakePayPal();
        $owner = User::factory()->owner()->create();
        $sub = new Subscription();
        $sub->forceFill(['user_id' => $owner->id, 'plan_id' => Plan::where('code', 'standard')->value('id'), 'billing_interval' => 'year',
            'status' => 'active', 'paypal_subscription_id' => 'I-NEW123', 'next_billing_at' => now()->addMonths(5)])->save();

        $this->actingAs($owner)->post('/account/billing/cancel')->assertRedirect('/account/billing');

        $sub->refresh();
        $this->assertSame('cancelled', $sub->status);
        $this->assertNotNull($sub->cancelled_at);
        $this->assertTrue($sub->ends_at->isSameDay(now()->addMonths(5)));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/I-NEW123/cancel'));
    }

    public function test_paypal_outage_is_reported_politely(): void
    {
        $this->fakePayPal([self::API.'/v1/billing/subscriptions' => Http::response(['name' => 'INTERNAL_SERVER_ERROR'], 500)]);

        $this->actingAs(User::factory()->owner()->create())->post('/account/billing/checkout', ['plan' => 'standard', 'interval' => 'year'])
            ->assertSessionHasErrors(['plan' => 'We could not reach PayPal. Please try again in a few minutes.']);
    }
}
