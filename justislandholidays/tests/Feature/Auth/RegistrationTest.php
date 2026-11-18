<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/account/register', $overrides + [
            'name' => 'Ana Owner',
            'email' => 'ana@example.com',
            'account_type' => 'owner',
            'password' => 'a-long-password-123',
            'password_confirmation' => 'a-long-password-123',
        ]);
    }

    public function test_sign_up_pages_render(): void
    {
        $this->get('/account/register')->assertOk()->assertSee('Create an account');
        $this->get('/account/login')->assertOk()->assertSee('Sign in');
    }

    public function test_owner_can_sign_up(): void
    {
        $this->register()->assertRedirect();

        $this->assertSame(Role::Owner, User::where('email', 'ana@example.com')->first()->role);
    }

    public function test_agent_can_sign_up(): void
    {
        $this->register(['account_type' => 'agent'])->assertRedirect();

        $this->assertSame(Role::Agent, User::where('email', 'ana@example.com')->first()->role);
    }

    /** Affiliates and admins are created by a super admin only. */
    public function test_cannot_sign_up_as_affiliate_or_admin(): void
    {
        foreach (['affiliate', 'island_admin', 'super_admin'] as $type) {
            $this->register(['account_type' => $type])->assertSessionHasErrors('account_type');
        }

        $this->assertSame(0, User::count());
    }

    public function test_role_field_is_ignored_on_sign_up(): void
    {
        $this->register(['role' => 'super_admin'])->assertRedirect();

        $this->assertSame(Role::Owner, User::first()->role);
    }

    public function test_unverified_user_must_confirm_email_first(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/account')->assertRedirect('/account/email/verify');
    }
}
