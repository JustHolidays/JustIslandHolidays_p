<?php

namespace Tests\Feature\Admin;

use App\Models\Island;
use App\Models\Listing;
use App\Models\PropertyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Property types: editable by super admins only. */
class PropertyTypeTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    public function test_the_six_original_types_exist_after_migrating(): void
    {
        $this->assertSame(['villa', 'apartment', 'townhouse', 'finca', 'bungalow', 'studio'], PropertyType::activeCodes());
        $this->assertSame('Ferienwohnung', PropertyType::label('apartment', 'de'));
        $this->assertSame('Villa', PropertyType::label('villa'));
    }

    public function test_super_admin_adds_a_type_and_owners_can_use_it(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->get('/admin')->assertSee('>Property types</a>', false);
        $this->actingAs($admin)->get(route('admin.property-types.index'))->assertOk()->assertSee('Townhouse');

        $this->actingAs($admin)->post(route('admin.property-types.store'), ['new_name_en' => 'Country house', 'new_code' => 'country_house'])
            ->assertRedirect(route('admin.property-types.index'))->assertSessionHasNoErrors();

        $type = PropertyType::where('code', 'country_house')->sole();
        $this->assertEqualsCanonicalizing(['en' => 'Country house', 'es' => 'Country house', 'de' => 'Country house'], $type->name);
        $this->assertContains('country_house', PropertyType::activeCodes());
        $this->assertDatabaseHas('audit_log', ['action' => 'property_type.create']);

        // The search filter offers it, with its name.
        $this->get('/en/search')->assertOk()->assertSee('Country house')->assertSee('value="country_house"', false);

        // A listing can be saved with it, and shows its name.
        $listing = Listing::factory()->live()->titled('Stone house')->create(['property_type' => 'country_house'])->load(['island', 'area']);
        $this->get($listing->publicUrl('en'))->assertOk()->assertSee('Country house');
    }

    public function test_renaming_changes_the_name_everywhere(): void
    {
        $admin = $this->superAdmin();
        $type = PropertyType::where('code', 'finca')->sole();
        $listing = Listing::factory()->live()->titled('Old farm')->create(['property_type' => 'finca'])->load(['island', 'area']);

        $this->actingAs($admin)->get(route('admin.property-types.edit', $type))->assertOk()->assertSee('1 listing uses it');
        $this->actingAs($admin)->put(route('admin.property-types.update', $type), ['name_en' => 'Finca (farmhouse)', 'name_es' => 'Finca', 'name_de' => '', 'sort_order' => 1, 'is_active' => 1])
            ->assertSessionHasNoErrors();

        $name = $type->fresh()->name;
        ksort($name);
        $this->assertSame(['de' => 'Finca (farmhouse)', 'en' => 'Finca (farmhouse)', 'es' => 'Finca'], $name, 'An empty language uses the English name.');
        $this->get($listing->publicUrl('en'))->assertSee('Finca (farmhouse)');
        $this->assertSame('finca', $type->fresh()->code, 'The code never changes.');
        $this->assertDatabaseHas('audit_log', ['action' => 'property_type.update']);
    }

    public function test_switching_off_hides_a_type_but_listings_keep_it(): void
    {
        $admin = $this->superAdmin();
        $type = PropertyType::where('code', 'studio')->sole();
        $listing = $this->setStatus($this->completeListing(), \App\Enums\ListingStatus::Draft);
        $listing->update(['property_type' => 'studio']);

        $this->actingAs($admin)->put(route('admin.property-types.update', $type), ['name_en' => 'Studio', 'sort_order' => 6, 'is_active' => 0])->assertSessionHasNoErrors();

        $this->assertNotContains('studio', PropertyType::activeCodes());
        $this->get('/en/search')->assertDontSee('value="studio"', false);
        $this->assertSame([], \App\Search\SearchFilters::fromRequest(request()->merge(['type' => ['studio']]))->types);

        // The owner can still edit a listing that has it, but cannot pick it for another.
        $this->assertContains('studio', PropertyType::allowedFor('studio'));
        $this->assertNotContains('studio', PropertyType::allowedFor('villa'));
        $this->actingAs($listing->owner)->get("/account/listings/{$listing->id}/edit/basics")->assertOk()->assertSee('value="studio"', false);
    }

    public function test_a_type_in_use_cannot_be_deleted_but_an_unused_one_can(): void
    {
        $admin = $this->superAdmin();
        Listing::factory()->create(['property_type' => 'villa']);
        $villa = PropertyType::where('code', 'villa')->sole();
        $bungalow = PropertyType::where('code', 'bungalow')->sole();

        $this->actingAs($admin)->delete(route('admin.property-types.destroy', $villa))->assertSessionHasErrors('type');
        $this->assertNotNull($villa->fresh());

        $this->actingAs($admin)->delete(route('admin.property-types.destroy', $bungalow))->assertRedirect(route('admin.property-types.index'));
        $this->assertNull($bungalow->fresh());
        $this->assertDatabaseHas('audit_log', ['action' => 'property_type.delete']);
    }

    public function test_bad_input_is_refused(): void
    {
        $admin = $this->superAdmin();
        $post = fn (array $d) => $this->actingAs($admin)->post(route('admin.property-types.store'), $d);

        $post(['new_name_en' => '', 'new_code' => 'x_house'])->assertSessionHasErrors('new_name_en');
        $post(['new_name_en' => 'Villa again', 'new_code' => 'villa'])->assertSessionHasErrors('new_code');
        $post(['new_name_en' => 'Bad', 'new_code' => 'Bad Code!'])->assertSessionHasErrors('new_code');
        $this->actingAs($admin)->put(route('admin.property-types.update', PropertyType::first()), ['name_en' => '', 'sort_order' => 1])->assertSessionHasErrors('name_en');

        $this->assertSame(6, PropertyType::count());
    }

    public function test_only_super_admins_manage_property_types(): void
    {
        $type = PropertyType::first();
        $islandAdmin = $this->islandAdmin(Island::factory()->create());

        $this->assertStringNotContainsString('Property types', $this->actingAs($islandAdmin)->get('/admin')->getContent());
        $this->actingAs($islandAdmin)->get(route('admin.property-types.index'))->assertForbidden();
        $this->actingAs($islandAdmin)->post(route('admin.property-types.store'), ['new_name_en' => 'X', 'new_code' => 'x_type'])->assertForbidden();
        $this->actingAs($islandAdmin)->put(route('admin.property-types.update', $type), ['name_en' => 'X', 'sort_order' => 1])->assertForbidden();
        $this->actingAs($islandAdmin)->delete(route('admin.property-types.destroy', $type))->assertForbidden();
        $this->actingAs(User::factory()->owner()->create())->get(route('admin.property-types.index'))->assertForbidden();

        $this->assertSame(6, PropertyType::count());
    }
}
