<?php

namespace Tests\Feature\Admin;

use App\Models\Island;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\AdminTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Colours of the admin area itself, set by super admins. */
class AdminColoursTest extends TestCase
{
    use RefreshDatabase;

    private function colours(array $o = []): array
    {
        return $o + ['header' => '#14532d', 'header_text' => '#ffffff', 'page' => '#fefce8', 'highlight' => '#dcfce7'];
    }

    public function test_admin_pages_use_the_original_colours_by_default(): void
    {
        $html = $this->actingAs($this->superAdmin())->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('--admin-header: #0f172a; --admin-header-text: #ffffff; --admin-page: #f1f5f9; --admin-highlight: #ffffff;', $html);
        $this->assertStringContainsString('<header class="bg-[var(--admin-header)] text-[var(--admin-header-text)]">', $html);
        $this->assertStringContainsString('bg-[var(--admin-page)]', $html);
        $this->assertStringContainsString('bg-[var(--admin-highlight)] font-semibold shadow-sm', $html, 'The current menu item uses the highlight.');
    }

    public function test_super_admin_changes_the_admin_colours_for_all_staff(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->get('/admin')->assertSee('>Admin Styler</a>', false);
        $this->actingAs($admin)->get(route('admin.admin-colours.edit'))->assertOk()->assertSee('Header bar')->assertSee('passes');

        $this->actingAs($admin)->put(route('admin.admin-colours.update'), $this->colours(['header' => '#14532D']))
            ->assertRedirect(route('admin.admin-colours.edit'))->assertSessionHasNoErrors();

        $this->assertSame('#14532d', AdminTheme::colours()['header']);
        $this->assertDatabaseHas('audit_log', ['action' => 'admin_colours.update']);

        // Island admins see the new colours too.
        $islandAdmin = $this->islandAdmin(Island::factory()->create());
        $this->actingAs($islandAdmin)->get('/admin')->assertOk()->assertSee('--admin-header: #14532d;', false);

        // The public site is unchanged.
        $this->get('/en')->assertDontSee('--admin-header', false);
    }

    public function test_unreadable_colours_are_refused(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->put(route('admin.admin-colours.update'), $this->colours(['header' => '#ffff00']))->assertSessionHasErrors('colours');
        $this->actingAs($admin)->put(route('admin.admin-colours.update'), $this->colours(['page' => '#334155']))->assertSessionHasErrors('colours');
        $this->actingAs($admin)->put(route('admin.admin-colours.update'), $this->colours(['highlight' => 'blue']))->assertSessionHasErrors('highlight');

        $this->assertNull(SiteSetting::find(AdminTheme::KEY));
        $this->assertSame(AdminTheme::DEFAULTS, AdminTheme::colours());
    }

    public function test_reset_puts_the_original_colours_back(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->put(route('admin.admin-colours.update'), $this->colours());

        $this->actingAs($admin)->put(route('admin.admin-colours.update'), ['reset' => 1])->assertSessionHasNoErrors();

        $this->assertSame(AdminTheme::DEFAULTS, AdminTheme::colours());
    }

    public function test_only_super_admins_change_the_admin_colours(): void
    {
        $islandAdmin = $this->islandAdmin(Island::factory()->create());

        $this->assertStringNotContainsString('Admin Styler', $this->actingAs($islandAdmin)->get('/admin')->getContent());
        $this->actingAs($islandAdmin)->get(route('admin.admin-colours.edit'))->assertForbidden();
        $this->actingAs($islandAdmin)->put(route('admin.admin-colours.update'), $this->colours())->assertForbidden();
        $this->actingAs(User::factory()->owner()->create())->put(route('admin.admin-colours.update'), $this->colours())->assertForbidden();

        $this->assertNull(SiteSetting::find(AdminTheme::KEY));
    }
}
