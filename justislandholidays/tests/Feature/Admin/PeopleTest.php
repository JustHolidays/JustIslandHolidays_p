<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus as S;
use App\Enums\Role;
use App\Models\AdminRecommendation;
use App\Models\Island;
use App\Models\User;
use App\Notifications\AccountNotice;
use App\Notifications\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

class PeopleTest extends TestCase
{
    use BuildsListings, RefreshDatabase;

    private Island $island;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        Notification::fake();
        $this->island = Island::factory()->create();
    }

    public function test_island_admin_sees_only_owners_with_listings_on_their_islands(): void
    {
        $here = $this->completeListing(island: $this->island)->owner;
        $there = $this->completeListing(island: Island::factory()->create())->owner;
        $admin = $this->islandAdmin($this->island);

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee($here->email)->assertDontSee($there->email);
        $this->actingAs($admin)->put("/admin/users/{$here->id}", ['name' => 'X', 'email' => $here->email, 'role' => 'owner'])->assertForbidden();
        $this->actingAs($admin)->post("/admin/users/{$here->id}/deactivate", ['reason' => 'Spam listings'])->assertForbidden();
    }

    public function test_island_admin_recommends_suspension_and_super_admin_decides(): void
    {
        $owner = $this->completeListing(island: $this->island)->owner;
        $listing = $this->setStatus($owner->listings()->first(), S::Live);
        $admin = $this->islandAdmin($this->island);

        $this->actingAs($admin)->post("/admin/users/{$owner->id}/recommend-suspension", ['reason' => 'Fake reviews'])->assertSessionHasNoErrors();
        $rec = AdminRecommendation::sole();
        $this->assertSame('suspend_user', $rec->action);

        $this->actingAs($this->superAdmin())->post("/admin/recommendations/{$rec->id}/decide", ['decision' => 'accept']);

        $this->assertTrue($owner->fresh()->isDeactivated());
        $this->assertSame(S::Paused, $listing->fresh()->status);
    }

    public function test_super_admin_deactivation_pauses_listings_ends_sessions_and_emails_the_reason(): void
    {
        $owner = User::factory()->owner()->create();
        $listing = $this->setStatus($this->completeListing($owner), S::Live);
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->superAdmin())->post("/admin/users/{$owner->id}/deactivate", ['reason' => 'Repeated spam'])->assertSessionHasNoErrors();

        $this->assertTrue($owner->fresh()->isDeactivated());
        $this->assertSame(S::Paused, $listing->fresh()->status);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $owner->id)->count());
        Notification::assertSentTo($owner, AccountNotice::class, fn ($n) => $n->kind === 'account.deactivated' && $n->reason === 'Repeated spam');
    }

    public function test_super_admin_edits_an_owner_and_changes_role(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($this->superAdmin())->put("/admin/users/{$owner->id}", ['name' => 'New Name', 'email' => 'new@example.com', 'role' => 'agent'])->assertSessionHasNoErrors();

        $this->assertSame(Role::Agent, $owner->fresh()->role);
        $this->assertSame('new@example.com', $owner->fresh()->email);
    }

    public function test_staff_invitation_flow_ends_with_two_factor_setup(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->post('/admin/staff', ['name' => 'Isla', 'email' => 'isla@example.com', 'role' => 'island_admin'])->assertSessionHasErrors('islands');
        $this->actingAs($super)->post('/admin/staff', ['name' => 'Isla', 'email' => 'isla@example.com', 'role' => 'island_admin', 'islands' => [$this->island->id]])->assertSessionHasNoErrors();

        $isla = User::where('email', 'isla@example.com')->sole();
        $this->assertTrue($isla->isIslandAdmin());
        $this->assertSame([$this->island->id], $isla->managedIslandIds());

        $token = null;
        Notification::assertSentTo($isla, Invitation::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        auth()->logout();
        $this->get("/account/invitation/{$token}?email=isla@example.com")->assertOk()->assertSee('Set your password');
        $this->post('/account/invitation', ['token' => $token, 'email' => 'isla@example.com', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1'])
            ->assertRedirect(route('account.security'));

        $this->assertAuthenticatedAs($isla);
        $this->assertNotNull($isla->fresh()->email_verified_at);
        $this->get('/admin')->assertRedirect(route('account.security'));
    }

    public function test_bad_invitation_token_is_refused(): void
    {
        $user = User::factory()->islandAdmin($this->island)->create(['email' => 'x@example.com']);

        $this->post('/account/invitation', ['token' => 'wrong', 'email' => 'x@example.com', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_nobody_changes_their_own_role_and_the_last_super_admin_stays(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->put("/admin/staff/{$super->id}", ['name' => $super->name, 'email' => $super->email, 'role' => 'island_admin', 'islands' => [$this->island->id]])
            ->assertSessionHasErrors('role');
        $this->actingAs($super)->post("/admin/staff/{$super->id}/deactivate", ['reason' => 'Leaving'])->assertSessionHasErrors();

        $this->assertTrue($super->fresh()->isSuperAdmin());
        $this->assertFalse($super->fresh()->isDeactivated());
    }

    public function test_island_changes_apply_and_two_factor_can_be_reset(): void
    {
        $super = $this->superAdmin();
        $admin = $this->islandAdmin($this->island);
        $second = Island::factory()->create();

        $this->actingAs($super)->put("/admin/staff/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'role' => 'island_admin', 'islands' => [$second->id]]);
        $this->assertSame([$second->id], $admin->fresh()->managedIslandIds());

        $this->actingAs($super)->post("/admin/staff/{$admin->id}/reset-two-factor");
        $this->assertFalse($admin->fresh()->hasConfirmedTwoFactor());
    }

    public function test_affiliate_created_by_super_admin_with_private_notes(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->post('/admin/affiliates', ['name' => 'Villa Partners', 'email' => 'p@partner.example', 'affiliate_data' => 'Programme ABC, partner id 77'])->assertRedirect();

        $affiliate = User::where('email', 'p@partner.example')->sole();
        $this->assertTrue($affiliate->isAffiliate());
        $this->assertSame('Programme ABC, partner id 77', $affiliate->affiliate_data);
        Notification::assertSentTo($affiliate, Invitation::class);

        $this->actingAs($super)->get("/admin/affiliates/{$affiliate->id}")->assertOk()->assertSee('partner id 77');
        $this->actingAs($this->islandAdmin($this->island))->get("/admin/affiliates/{$affiliate->id}")->assertForbidden();
    }

    public function test_deactivating_an_affiliate_pauses_their_listings(): void
    {
        $affiliate = User::factory()->affiliate()->create();
        $listing = $this->setStatus($this->completeListing($affiliate, attributes: ['deeplink_url' => 'https://b.example/1']), S::Live);

        $this->actingAs($this->superAdmin())->post("/admin/affiliates/{$affiliate->id}/deactivate", ['reason' => 'Partnership ended']);

        $this->assertSame(S::Paused, $listing->fresh()->status);
    }

    public function test_affiliate_notes_never_reach_the_audit_log(): void
    {
        $super = $this->superAdmin();
        $affiliate = User::factory()->affiliate()->create();

        $this->actingAs($super)->put("/admin/affiliates/{$affiliate->id}", ['name' => $affiliate->name, 'email' => $affiliate->email, 'affiliate_data' => 'SECRET-TERMS-123']);

        $this->assertStringNotContainsString('SECRET-TERMS-123', json_encode(\App\Models\AuditLog::pluck('detail')));
    }
}
