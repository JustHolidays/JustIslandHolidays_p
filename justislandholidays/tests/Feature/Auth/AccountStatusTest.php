<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** users.deactivated_at and users.last_login_at */
class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_account_cannot_sign_in_with_the_right_password(): void
    {
        User::factory()->owner()->deactivated()->create(['email' => 'off@example.com']);

        $this->post('/account/login', ['email' => 'off@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivated_admin_does_not_reach_the_two_factor_step(): void
    {
        User::factory()->superAdmin()->deactivated()->create(['email' => 'off@example.com']);

        $this->post('/account/login', ['email' => 'off@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertNull(session('login.id'));
    }

    public function test_signed_in_account_is_signed_out_once_deactivated(): void
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner)->get('/account')->assertOk();

        $owner->forceFill(['deactivated_at' => now()])->save();

        $this->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivated_admin_is_signed_out_of_the_admin_area(): void
    {
        $admin = User::factory()->superAdmin()->deactivated()->create();

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivation_is_not_deletion(): void
    {
        $owner = User::factory()->owner()->deactivated()->create();

        $this->assertNotNull(User::find($owner->id), 'Deactivated accounts stay visible to admins.');
        $this->assertFalse($owner->trashed());
    }

    public function test_sign_in_records_last_login(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'ana@example.com']);
        $this->assertNull($owner->last_login_at);

        $this->post('/account/login', ['email' => 'ana@example.com', 'password' => 'password'])
            ->assertRedirect('/account');

        $this->assertNotNull($owner->fresh()->last_login_at);
    }

    public function test_last_login_is_recorded_only_after_the_two_factor_challenge(): void
    {
        $admin = User::factory()->superAdmin()->create(['email' => 'boss@example.com']);

        $this->post('/account/login', ['email' => 'boss@example.com', 'password' => 'password'])
            ->assertRedirect('/account/two-factor-challenge');
        $this->assertNull($admin->fresh()->last_login_at);

        $this->post('/account/two-factor-challenge', ['recovery_code' => 'code-one'])
            ->assertRedirect('/account');
        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_deactivated_super_admin_does_not_count_towards_the_last_one(): void
    {
        $active = $this->superAdmin();
        $deactivated = User::factory()->superAdmin()->deactivated()->create();

        $this->assertFalse($deactivated->can('changeRole', $active), 'The only active super admin stays.');
        $this->assertFalse($deactivated->can('deactivate', $active));
    }
}
