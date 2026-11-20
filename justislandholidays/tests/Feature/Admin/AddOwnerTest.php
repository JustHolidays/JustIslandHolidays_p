<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Island;
use App\Models\User;
use App\Notifications\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Super admins add owner and agent accounts (20 Nov 2026). */
class AddOwnerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = $this->superAdmin();
    }

    public function test_super_admin_adds_an_owner_who_is_invited(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertSee('Add an owner')->assertSee(route('admin.users.create'), false);
        $this->actingAs($this->admin)->get(route('admin.users.create'))->assertOk()->assertSee('value="owner" class="h-5 w-5" checked', false);

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'role' => 'owner', 'name' => 'Olivia Owner', 'email' => ' Olivia@Owner.example ', 'phone' => '+44 7700 900111', 'invite' => '1',
        ])->assertSessionHasNoErrors();

        $owner = User::where('email', 'olivia@owner.example')->sole();
        $this->assertSame(Role::Owner, $owner->role);
        $this->assertNull($owner->email_verified_at, 'Confirmed when they set their password.');
        Notification::assertSentTo($owner, Invitation::class);
        $this->assertDatabaseHas('audit_log', ['action' => 'user.admin_create', 'actor_id' => $this->admin->id]);
        $this->actingAs($this->admin)->get(route('admin.users.show', $owner))->assertOk()
            ->assertSee('Add a property for them')->assertSee('Send invitation to set a password');
    }

    public function test_an_agent_can_be_added_without_an_invitation_and_invited_later(): void
    {
        $this->actingAs($this->admin)->get(route('admin.agents.index'))->assertSee('Add an agent');

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'role' => 'agent', 'name' => 'Andy Agent', 'email' => 'andy@agent.example',
        ])->assertRedirect();

        $agent = User::where('email', 'andy@agent.example')->sole();
        $this->assertSame(Role::Agent, $agent->role);
        Notification::assertNothingSent();

        $this->actingAs($this->admin)->post(route('admin.users.invite', $agent))->assertRedirect();
        Notification::assertSentTo($agent, Invitation::class);
    }

    public function test_choices_are_checked(): void
    {
        User::factory()->owner()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->admin)->post(route('admin.users.store'), ['role' => 'owner', 'name' => 'X', 'email' => 'TAKEN@example.com'])->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->post(route('admin.users.store'), ['role' => 'super_admin', 'name' => 'X', 'email' => 'new@example.com'])->assertSessionHasErrors('role');
        $this->actingAs($this->admin)->post(route('admin.users.store'), ['role' => 'owner', 'email' => 'new@example.com'])->assertSessionHasErrors('name');
        $this->assertNull(User::where('email', 'new@example.com')->first());
    }

    public function test_only_super_admins_add_owners(): void
    {
        $islandAdmin = $this->islandAdmin(Island::factory()->create());

        $this->actingAs($islandAdmin)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($islandAdmin)->post(route('admin.users.store'), ['role' => 'owner', 'name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
        $this->actingAs($islandAdmin)->get(route('admin.users.index'))->assertDontSee('Add an owner');
        $this->assertNull(User::where('email', 'x@example.com')->first());

        $affiliate = User::factory()->affiliate()->create();
        $this->actingAs($this->admin)->post(route('admin.users.invite', $affiliate))->assertNotFound();
    }
}
