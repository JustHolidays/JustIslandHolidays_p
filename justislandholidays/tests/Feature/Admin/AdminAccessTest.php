<?php

namespace Tests\Feature\Admin;

use App\Models\Island;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private const SUPER_ADMIN_PAGES = ['/admin/staff', '/admin/affiliates', '/admin/appearance', '/admin/plans', '/admin/audit'];

    public function test_guest_is_sent_to_sign_in(): void
    {
        $this->get('/admin')->assertRedirect('/account/login');
    }

    public function test_owners_agents_and_affiliates_are_refused(): void
    {
        foreach (['owner', 'agent', 'affiliate'] as $state) {
            $user = User::factory()->{$state}()->create();
            $this->actingAs($user)->get('/admin')->assertForbidden();
        }
    }

    public function test_admin_without_two_factor_is_sent_to_set_it_up(): void
    {
        $admin = User::factory()->superAdmin()->withoutTwoFactor()->create();

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('account.security'));
        $this->actingAs($admin)->get('/account/security')->assertOk()->assertSee('Admin accounts must use two-factor sign-in');
    }

    public function test_island_admin_with_no_islands_is_refused(): void
    {
        $admin = User::factory()->islandAdmin()->create();

        $this->actingAs($admin)->get('/admin')->assertForbidden();
    }

    public function test_island_admin_with_an_island_can_open_the_dashboard(): void
    {
        $admin = $this->islandAdmin(Island::factory()->create());

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_super_admin_can_open_the_dashboard(): void
    {
        $this->actingAs($this->superAdmin())->get('/admin')->assertOk();
    }

    public function test_island_admin_is_refused_super_admin_pages(): void
    {
        $admin = $this->islandAdmin(Island::factory()->create());

        foreach (self::SUPER_ADMIN_PAGES as $url) {
            $this->actingAs($admin)->get($url)->assertForbidden();
        }
    }

    public function test_super_admin_can_open_super_admin_pages(): void
    {
        $admin = $this->superAdmin();

        foreach (self::SUPER_ADMIN_PAGES as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_deactivated_admin_cannot_sign_in(): void
    {
        $admin = User::factory()->superAdmin()->create(['email' => 'gone@example.com']);
        $admin->delete();

        $this->post('/account/login', ['email' => 'gone@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_with_two_factor_must_pass_the_challenge_to_sign_in(): void
    {
        User::factory()->superAdmin()->create(['email' => 'boss@example.com']);

        $this->post('/account/login', ['email' => 'boss@example.com', 'password' => 'password'])
            ->assertRedirect('/account/two-factor-challenge');
        $this->assertGuest();
    }
}
