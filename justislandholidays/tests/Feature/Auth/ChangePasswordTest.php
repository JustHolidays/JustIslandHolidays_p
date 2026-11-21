<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Change password on Password and sign-in (21 Nov 2026). */
class ChangePasswordTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->owner()->create(['password' => Hash::make('old-password-1')]);
    }

    public function test_every_account_has_the_form_on_its_security_page(): void
    {
        $this->seedPlans();
        foreach ([User::factory()->owner()->create(), User::factory()->agent()->create(), User::factory()->create(['role' => Role::Visitor]), $this->superAdmin()] as $user) {
            $this->actingAs($user)->get(route('account.security'))->assertOk()
                ->assertSee('Change your password')
                ->assertSee('action="'.route('user-password.update').'"', false)
                ->assertSee('autocomplete="current-password"', false)
                ->assertSee('Password and sign-in');
        }
    }

    public function test_admin_header_links_to_it(): void
    {
        $this->seedPlans();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('href="'.route('account.security').'"', false)->assertSee('Password and sign-in');
    }

    public function test_the_password_changes_with_the_right_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from(route('account.security'))->put(route('user-password.update'), [
            'current_password' => 'old-password-1',
            'password' => 'new-password-2',
            'password_confirmation' => 'new-password-2',
        ])->assertRedirect(route('account.security'))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-2', $user->fresh()->password));
        $this->assertTrue(AuditLog::where('action', 'user.password_change')->where('entity_id', (string) $user->id)->exists());
        $this->actingAs($user)->get(route('account.security'))->assertSee('Your password has been changed.')->assertDontSee('password-updated');
    }

    public function test_wrong_current_password_short_or_unmatched_new_password_is_refused(): void
    {
        $user = $this->user();
        $cases = [
            ['current_password' => 'wrong', 'password' => 'new-password-2', 'password_confirmation' => 'new-password-2'],
            ['current_password' => 'old-password-1', 'password' => 'short', 'password_confirmation' => 'short'],
            ['current_password' => 'old-password-1', 'password' => 'new-password-2', 'password_confirmation' => 'something-else'],
        ];
        $fields = ['current_password', 'password', 'password'];

        foreach ($cases as $i => $input) {
            $this->actingAs($user)->from(route('account.security'))->put(route('user-password.update'), $input)
                ->assertRedirect(route('account.security'))->assertSessionHasErrors($fields[$i]);
        }

        $this->assertTrue(Hash::check('old-password-1', $user->fresh()->password));
        $this->actingAs($user)->from(route('account.security'))
            ->put(route('user-password.update'), $cases[0])->assertSessionHasErrors(['current_password' => 'The provided password does not match your current password.']);
        $this->assertFalse(AuditLog::where('action', 'user.password_change')->exists());
    }

    public function test_signed_out_visitors_cannot_use_it(): void
    {
        $this->put(route('user-password.update'), ['current_password' => 'x', 'password' => 'new-password-2', 'password_confirmation' => 'new-password-2'])
            ->assertRedirect();
        $this->get(route('account.security'))->assertRedirect();
    }
}
