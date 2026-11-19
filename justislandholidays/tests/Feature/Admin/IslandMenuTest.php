<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\Island;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Islands and resorts dropdown in the admin menu, the per-island page and Add a location. */
class IslandMenuTest extends TestCase
{
    use RefreshDatabase;

    private Island $menorca;

    private Island $mallorca;

    private Island $ibiza;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menorca = Island::factory()->create(['slug' => 'menorca', 'name' => ['en' => 'Menorca'], 'is_active' => true, 'sort_order' => 1]);
        $this->mallorca = Island::factory()->create(['slug' => 'mallorca', 'name' => ['en' => 'Mallorca'], 'is_active' => true, 'sort_order' => 2]);
        $this->ibiza = Island::factory()->create(['slug' => 'ibiza', 'name' => ['en' => 'Ibiza'], 'is_active' => false, 'sort_order' => 3]);
    }

    public function test_menu_lists_active_islands_only(): void
    {
        $html = $this->actingAs($this->superAdmin())->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString(route('admin.islands.show', $this->menorca), $html);
        $this->assertStringContainsString(route('admin.islands.show', $this->mallorca), $html);
        $this->assertStringNotContainsString(route('admin.islands.show', $this->ibiza), $html);
        $this->assertStringContainsString('>All islands</a>', $html);
        $this->assertStringContainsString('>Add a location</a>', $html);
    }

    public function test_island_admin_sees_only_their_islands_and_no_add_a_location(): void
    {
        $admin = $this->islandAdmin($this->menorca);

        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString(route('admin.islands.show', $this->menorca), $html);
        $this->assertStringNotContainsString(route('admin.islands.show', $this->mallorca), $html);
        $this->assertStringNotContainsString('Add a location', $html);
    }

    public function test_dropdown_opens_on_island_pages_and_marks_the_current_one(): void
    {
        $admin = $this->superAdmin();

        $this->assertDoesNotMatchRegularExpression('/<details\s+open/', $this->actingAs($admin)->get('/admin')->assertOk()->getContent());

        $html = $this->actingAs($admin)->get(route('admin.islands.show', $this->menorca))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<details\s+open/', $html);
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>Menorca<\/a>/', $html);
        $this->assertDoesNotMatchRegularExpression('/aria-current="page"\s*>Mallorca<\/a>/', $html);

        // A resort's edit page opens the dropdown too, on its own island.
        $area = Area::factory()->create(['island_id' => $this->mallorca->id]);
        $this->assertMatchesRegularExpression('/<details\s+open/', $this->actingAs($admin)->get(route('admin.areas.edit', $area))->assertOk()->getContent());
    }

    public function test_island_page_shows_that_island_and_its_resorts(): void
    {
        Area::factory()->create(['island_id' => $this->menorca->id, 'name' => ['en' => 'Cala Galdana']]);
        Area::factory()->create(['island_id' => $this->mallorca->id, 'name' => ['en' => 'Pollença']]);

        $this->actingAs($this->superAdmin())->get(route('admin.islands.show', $this->menorca))->assertOk()
            ->assertSee('Cala Galdana')
            ->assertDontSee('Pollença');
    }

    public function test_island_admin_cannot_open_another_islands_page_by_url(): void
    {
        $admin = $this->islandAdmin($this->menorca);

        $this->actingAs($admin)->get(route('admin.islands.show', $this->menorca))->assertOk();
        $this->actingAs($admin)->get(route('admin.islands.show', $this->mallorca))->assertForbidden();
    }

    public function test_switched_off_islands_still_open_from_all_islands(): void
    {
        $this->actingAs($this->superAdmin())->get(route('admin.islands.index'))->assertOk()->assertSee('Ibiza')->assertSee('Switched off');
        $this->actingAs($this->superAdmin())->get(route('admin.islands.show', $this->ibiza))->assertOk();
    }

    // --- Add a location ----------------------------------------------------------------

    public function test_super_admin_adds_a_resort_choosing_the_island(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.locations.create', ['island' => $this->mallorca->id]))->assertOk()
            ->assertSee('<option value="'.$this->mallorca->id.'" selected', false)
            ->assertSee('Ibiza (switched off)');

        $this->actingAs($admin)->post(route('admin.locations.area'), ['island' => $this->mallorca->id, 'name_en' => 'Sóller', 'slug' => 'soller', 'kind' => 'town'])
            ->assertRedirect();

        $area = Area::where('slug', 'soller')->sole();
        $this->assertSame($this->mallorca->id, $area->island_id);
        $this->assertDatabaseHas('audit_log', ['action' => 'area.create']);
    }

    public function test_resort_form_needs_an_island_and_a_unique_slug(): void
    {
        $admin = $this->superAdmin();
        Area::factory()->create(['island_id' => $this->menorca->id, 'slug' => 'son-bou']);

        $this->actingAs($admin)->post(route('admin.locations.area'), ['name_en' => 'X', 'slug' => 'x', 'kind' => 'town'])->assertSessionHasErrors('island');
        $this->actingAs($admin)->post(route('admin.locations.area'), ['island' => $this->menorca->id, 'name_en' => 'Son Bou', 'slug' => 'son-bou', 'kind' => 'town'])->assertSessionHasErrors('slug');
        // The same web address part is fine on another island.
        $this->actingAs($admin)->post(route('admin.locations.area'), ['island' => $this->mallorca->id, 'name_en' => 'Son Bou', 'slug' => 'son-bou', 'kind' => 'town'])->assertSessionHasNoErrors();
    }

    public function test_super_admin_adds_an_island_switched_off(): void
    {
        $this->actingAs($this->superAdmin())->post(route('admin.islands.store'), ['island_name' => 'Formentera', 'island_slug' => 'formentera'])->assertRedirect();

        $this->assertFalse(Island::where('slug', 'formentera')->sole()->is_active);
    }

    public function test_island_form_errors_do_not_show_on_the_resort_form(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->from(route('admin.locations.create'))
            ->post(route('admin.islands.store'), ['island_name' => '', 'island_slug' => 'menorca'])
            ->assertSessionHasErrors(['island_name', 'island_slug'])
            ->assertSessionDoesntHaveErrors(['name_en', 'slug']);
    }

    public function test_only_super_admins_can_add_a_location(): void
    {
        $admin = $this->islandAdmin($this->menorca);

        $this->actingAs($admin)->get(route('admin.locations.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.locations.area'), ['island' => $this->menorca->id, 'name_en' => 'X', 'slug' => 'x', 'kind' => 'town'])->assertForbidden();
        $this->actingAs(User::factory()->owner()->create())->get(route('admin.locations.create'))->assertForbidden();
    }
}
