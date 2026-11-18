<?php

namespace Tests\Feature\Policies;

use App\Models\Island;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_only_abilities(): void
    {
        $super = $this->superAdmin();
        $islandAdmin = $this->islandAdmin(Island::factory()->create());
        $owner = User::factory()->owner()->create();

        foreach (AppServiceProvider::SUPER_ADMIN_ABILITIES as $ability) {
            $this->assertTrue($super->can($ability), "super admin: {$ability}");
            $this->assertFalse($islandAdmin->can($ability), "island admin: {$ability}");
            $this->assertFalse($owner->can($ability), "owner: {$ability}");
        }
    }

    public function test_admin_area_and_click_reports_are_for_admins(): void
    {
        $this->assertTrue($this->superAdmin()->can('access-admin'));
        $this->assertTrue($this->islandAdmin()->can('view-click-reports'));

        foreach (['owner', 'agent', 'affiliate'] as $state) {
            $user = User::factory()->{$state}()->create();
            $this->assertFalse($user->can('access-admin'), $state);
            $this->assertFalse($user->can('view-click-reports'), $state);
        }
    }

    /** Affiliates have no plan, subscription or payments. */
    public function test_billing_is_for_owners_and_agents_only(): void
    {
        $this->assertTrue(User::factory()->owner()->create()->can('use-billing'));
        $this->assertTrue(User::factory()->agent()->create()->can('use-billing'));
        $this->assertFalse(User::factory()->affiliate()->create()->can('use-billing'));
        $this->assertFalse($this->superAdmin()->can('use-billing'));
    }

    public function test_listers_see_their_own_clicks(): void
    {
        foreach (['owner', 'agent', 'affiliate'] as $state) {
            $this->assertTrue(User::factory()->{$state}()->create()->can('view-own-clicks'), $state);
        }
        $this->assertFalse($this->superAdmin()->can('view-own-clicks'));
    }
}
