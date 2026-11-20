<?php

namespace Tests\Feature\Account;

use App\Mail\PlanUpgradeRequested;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Owners and agents ask for a plan upgrade from Plan and billing; super admins are emailed (20 Nov 2026). */
class PlanUpgradeRequestTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        Mail::fake();
    }

    public function test_owner_sees_the_form_with_plans_for_owners(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get(route('account.billing'))->assertOk()
            ->assertSee('Ask us about a plan upgrade')
            ->assertSee('value="standard"', false)->assertSee('value="featured"', false)->assertSee('value="other"', false)
            ->assertDontSee('id="wanted_free"', false)->assertDontSee('id="wanted_agent_plus"', false);
    }

    public function test_request_is_emailed_to_every_super_admin_with_reply_to_the_owner(): void
    {
        $first = $this->superAdmin();
        $second = $this->superAdmin();
        $deactivated = $this->superAdmin();
        $deactivated->forceFill(['deactivated_at' => now()])->save();
        $islandAdmin = $this->islandAdmin(\App\Models\Island::factory()->create());
        $owner = User::factory()->owner()->create(['name' => 'Olivia Owner', 'email' => 'olivia@owner.example']);
        $this->completeListing($owner);

        $this->actingAs($owner)->post(route('account.billing.request-upgrade'), ['wanted' => 'featured', 'note' => 'I would like to be featured from May.'])
            ->assertSessionHasNoErrors()->assertRedirect(route('account.billing').'#upgrade')->assertSessionHas('upgrade_sent');

        // Every active super admin, including the one the island admin factory makes to assign them.
        $active = User::where('role', 'super_admin')->whereNull('deactivated_at')->pluck('email');
        $this->assertCount(3, $active);
        Mail::assertQueued(PlanUpgradeRequested::class, $active->count());
        foreach ([$first, $second] as $admin) {
            Mail::assertQueued(PlanUpgradeRequested::class, fn (PlanUpgradeRequested $m) => $m->hasTo($admin->email)
                && $m->hasReplyTo('olivia@owner.example', 'Olivia Owner')
                && $m->wanted === 'Featured' && $m->currentPlan === 'Free' && $m->listingsHeld === 1);
        }
        Mail::assertNotQueued(PlanUpgradeRequested::class, fn ($m) => $m->hasTo($deactivated->email) || $m->hasTo($islandAdmin->email));
        $this->assertDatabaseHas('audit_log', ['action' => 'plan.upgrade_request', 'actor_id' => $owner->id]);

        $mail = new PlanUpgradeRequested($owner, 'Free Plan', 'Featured Plan', 'I would like to be featured from May.', 1);
        $mail->assertSeeInHtml('Olivia Owner')->assertSeeInHtml('Featured Plan')->assertSeeInHtml('featured from May')->assertSeeInText('Gift A Plan');

        $this->actingAs($owner)->get(route('account.billing'))->assertSee('Your request has been sent');
    }

    public function test_choices_are_checked(): void
    {
        $owner = User::factory()->owner()->create();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);   // more tries than the 3 an hour allowed

        $this->actingAs($owner)->post(route('account.billing.request-upgrade'), [])->assertSessionHasErrors('wanted');
        $this->actingAs($owner)->post(route('account.billing.request-upgrade'), ['wanted' => 'agent_plus'])->assertSessionHasErrors('wanted');
        $this->actingAs($owner)->post(route('account.billing.request-upgrade'), ['wanted' => 'free'])->assertSessionHasErrors('wanted');
        $this->actingAs($owner)->post(route('account.billing.request-upgrade'), ['wanted' => 'other'])->assertSessionHasErrors('note');
        $this->actingAs($owner)->post(route('account.billing.request-upgrade'), ['wanted' => 'standard', 'note' => str_repeat('x', 1001)])->assertSessionHasErrors('note');

        Mail::assertNothingQueued();
    }

    public function test_agents_ask_for_agent_plans_and_affiliates_cannot_ask(): void
    {
        $this->superAdmin();
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)->get(route('account.billing'))->assertSee('id="wanted_agent_plus"', false)->assertDontSee('id="wanted_standard"', false);
        $this->actingAs($agent)->post(route('account.billing.request-upgrade'), ['wanted' => 'agent_plus'])->assertSessionHasNoErrors();
        Mail::assertQueued(PlanUpgradeRequested::class, fn ($m) => $m->wanted === 'Agent Plus');
    }

    public function test_requests_are_limited_to_three_an_hour(): void
    {
        $owner = User::factory()->owner()->create();
        foreach (range(1, 3) as $i) {
            $this->actingAs($owner)->post(route('account.billing.request-upgrade'), ['wanted' => 'standard'])->assertRedirect();
        }
        $this->actingAs($owner)->post(route('account.billing.request-upgrade'), ['wanted' => 'standard'])->assertStatus(429);

        $this->actingAs(User::factory()->affiliate()->create())->post(route('account.billing.request-upgrade'), ['wanted' => 'other', 'note' => 'x'])->assertForbidden();
    }
}
