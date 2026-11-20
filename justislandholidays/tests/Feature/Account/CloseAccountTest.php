<?php

namespace Tests\Feature\Account;

use App\Enums\ListingStatus;
use App\Mail\AccountClosureRequested;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AccountNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** "Close my account": a request super admins close or decline (20 Nov 2026). */
class CloseAccountTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        Mail::fake();
        Notification::fake();
    }

    private function ask(User $user, string $reason = 'Sold the villa'): void
    {
        $this->actingAs($user)->post(route('account.close.store'), ['closure_reason' => $reason])->assertSessionHasNoErrors();
    }

    public function test_owners_agents_and_visitors_see_the_button_others_do_not(): void
    {
        foreach ([User::factory()->owner()->create(), User::factory()->agent()->create(), User::factory()->visitor()->create()] as $user) {
            $this->actingAs($user)->get(route('account.home'))->assertOk()->assertSee('Close my account')->assertSee(route('account.close.store'), false);
        }
        $this->actingAs(User::factory()->affiliate()->create())->get(route('account.home'))->assertDontSee('id="close-account"', false);
        $this->actingAs(User::factory()->affiliate()->create())->post(route('account.close.store'))->assertForbidden();
    }

    public function test_a_request_is_stored_emailed_to_super_admins_and_can_be_withdrawn(): void
    {
        $admin = $this->superAdmin();
        $owner = User::factory()->owner()->create(['name' => 'Olivia Owner']);

        $this->ask($owner);

        $owner->refresh();
        $this->assertNotNull($owner->closure_requested_at);
        $this->assertSame('Sold the villa', $owner->closure_reason);
        Mail::assertQueued(AccountClosureRequested::class, fn ($m) => $m->hasTo($admin->email) && $m->hasReplyTo($owner->email));
        $this->assertDatabaseHas('audit_log', ['action' => 'user.closure_request']);
        $this->actingAs($owner)->get(route('account.home'))->assertSee('You asked us to close your account')->assertSee('Withdraw my request');

        (new AccountClosureRequested($owner))->assertSeeInHtml('Sold the villa')->assertSeeInHtml(route('admin.closures.index'));

        $this->actingAs($owner)->delete(route('account.close.destroy'))->assertRedirect();
        $this->assertNull($owner->fresh()->closure_requested_at);
    }

    public function test_super_admin_closes_the_account(): void
    {
        $admin = $this->superAdmin();
        $owner = User::factory()->owner()->create(['name' => 'Olivia Owner']);
        $live = $this->setStatus($this->completeListing($owner), ListingStatus::Live);
        $this->ask($owner);

        $this->actingAs($admin)->get(route('admin.closures.index'))->assertOk()
            ->assertSee('Olivia Owner')->assertSee('Sold the villa')->assertSee('1 listing still up');

        $this->actingAs($admin)->post(route('admin.closures.close', $owner))->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSoftDeleted($owner);
        $this->assertSame(ListingStatus::Archived, $live->fresh()->status);
        Notification::assertSentTo($owner, AccountNotice::class, fn ($n) => $n->kind === 'account.closed');
        $this->assertDatabaseHas('audit_log', ['action' => 'user.close', 'actor_id' => $admin->id]);
        $this->actingAs($admin)->get(route('admin.closures.index'))->assertSee('No requests waiting');

        // The nightly job removes their personal details 30 days later, favourites included.
        DB::table('favourites')->insert(['user_id' => $owner->id, 'listing_id' => $live->id, 'created_at' => now()]);
        $this->travel(31)->days();
        $this->artisan('data:retention')->assertSuccessful();
        $purged = User::withTrashed()->find($owner->id);
        $this->assertSame('Deleted account', $purged->name);
        $this->assertNull($purged->closure_reason);
        $this->assertSame(0, DB::table('favourites')->where('user_id', $owner->id)->count());
    }

    public function test_a_paid_plan_is_cancelled_in_paypal_and_a_failure_stops_the_closure(): void
    {
        config(['services.paypal' => ['mode' => 'sandbox', 'client_id' => 'id', 'client_secret' => 'secret', 'webhook_id' => 'WH', 'brand_name' => 'JIH']]);
        $admin = $this->superAdmin();
        $owner = User::factory()->owner()->create();
        (new Subscription())->forceFill([
            'user_id' => $owner->id, 'plan_id' => Plan::where('code', 'standard')->value('id'),
            'billing_interval' => 'year', 'status' => 'active', 'paypal_subscription_id' => 'I-SUB123', 'next_billing_at' => now()->addYear(),
        ])->save();
        $this->ask($owner);

        // PayPal fails (it is tried twice), then works on the next attempt.
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            '*/cancel' => Http::sequence()->push('', 500)->push('', 500)->push('', 204),
        ]);
        $this->actingAs($admin)->from(route('admin.closures.index'))->post(route('admin.closures.close', $owner))->assertSessionHasErrors('close');
        $this->assertNotSoftDeleted($owner);

        $this->actingAs($admin)->post(route('admin.closures.close', $owner))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($owner);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/billing/subscriptions/I-SUB123/cancel'));
    }

    public function test_super_admin_declines_with_a_reason(): void
    {
        $admin = $this->superAdmin();
        $visitor = User::factory()->visitor()->create();
        $this->ask($visitor, '');

        $this->actingAs($admin)->from(route('admin.closures.index'))->post(route('admin.closures.decline', $visitor), ['reason' => ''])
            ->assertSessionHasErrorsIn('decline'.$visitor->id, 'reason');
        $this->actingAs($admin)->post(route('admin.closures.decline', $visitor), ['reason' => 'Please confirm by replying from your own email first.'])->assertRedirect();

        $this->assertNull($visitor->fresh()->closure_requested_at);
        $this->assertNotSoftDeleted($visitor);
        Notification::assertSentTo($visitor, AccountNotice::class, fn ($n) => $n->kind === 'account.closure_declined' && str_contains($n->reason, 'replying'));
    }

    public function test_only_super_admins_action_requests_and_only_waiting_ones(): void
    {
        $owner = User::factory()->owner()->create();
        $islandAdmin = $this->islandAdmin(\App\Models\Island::factory()->create());

        $this->actingAs($this->superAdmin())->post(route('admin.closures.close', $owner))->assertForbidden();   // never asked
        $this->ask($owner);
        $this->actingAs($islandAdmin)->get(route('admin.closures.index'))->assertForbidden();
        $this->actingAs($islandAdmin)->post(route('admin.closures.close', $owner))->assertForbidden();
        $this->assertNotSoftDeleted($owner);
    }
}
