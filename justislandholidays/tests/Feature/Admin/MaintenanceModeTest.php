<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\User;
use App\Support\Maintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Maintenance mode: visitors see a maintenance page, admins see the site (20 Nov 2026). */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->admin = $this->superAdmin();
        $this->listing = Listing::factory()->live()->titled('Villa Sunset')->create()->load(['island', 'area']);
    }

    private function switchOn(array $extra = []): void
    {
        $this->actingAs($this->admin)->put(route('admin.maintenance.update'), $extra + ['on' => '1'])->assertSessionHasNoErrors();
        auth()->logout();
    }

    public function test_super_admin_switches_it_on_and_visitors_see_the_maintenance_page(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertSee('>Maintenance mode</a>', false);
        $this->actingAs($this->admin)->get(route('admin.maintenance.edit'))->assertOk()->assertSee('data-maintenance="off"', false);

        $this->switchOn(['title' => 'Back soon', 'message' => "New things are coming.\n\nSee you shortly.", 'until' => now()->addHours(3)->format('Y-m-d\TH:i')]);
        $this->assertDatabaseHas('audit_log', ['action' => 'site.maintenance']);

        foreach ([route('home', 'en'), route('islands.show', ['en', $this->listing->island->slug]), $this->listing->publicUrl('en'), route('search', 'en'), route('owners', 'en')] as $url) {
            $this->get($url)->assertStatus(503)->assertSee('Back soon')->assertSee('New things are coming.')->assertSee('We expect to be back by')
                ->assertHeader('Retry-After')->assertDontSee('Villa Sunset');
        }
        $this->post(route('enquiries.store', 'en'), ['listing_ref' => $this->listing->public_ref])->assertStatus(503);
    }

    public function test_admins_still_see_and_use_the_site(): void
    {
        $this->switchOn();
        $islandAdmin = $this->islandAdmin($this->listing->island);

        foreach ([$this->admin, $islandAdmin] as $admin) {
            $this->actingAs($admin)->get($this->listing->publicUrl('en'))->assertOk()->assertSee('Villa Sunset')->assertSee('data-maintenance-banner', false);
            $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Maintenance mode is on: visitors see the maintenance page.');
            $this->actingAs($admin)->get(route('home', ['en', 'maintenance' => 'preview']))->assertOk()->assertSee('Preview: this is what visitors see');
        }
        auth()->logout();
    }

    public function test_sign_in_accounts_and_the_rest_keep_working(): void
    {
        $this->switchOn();
        $owner = User::factory()->owner()->create();

        $this->get(route('login'))->assertOk();
        $this->actingAs($owner)->get(route('account.home'))->assertOk();
        $this->actingAs($owner)->get($this->listing->publicUrl('en'))->assertStatus(503);   // owners are visitors here
        auth()->logout();
        $this->get('/robots.txt')->assertOk();
        $this->get('/sitemap.xml')->assertOk();
        $this->get(route('home', ['en', 'maintenance' => 'preview']))->assertStatus(503)->assertDontSee('Preview: this is what visitors see');
    }

    public function test_switching_off_opens_the_site_and_defaults_are_friendly(): void
    {
        $this->switchOn();
        $this->get(route('home', 'en'))->assertStatus(503)->assertSee(Maintenance::DEFAULT_TITLE)->assertSee('holiday of its own');

        $this->actingAs($this->admin)->put(route('admin.maintenance.update'), ['on' => '0']);
        auth()->logout();
        $this->get(route('home', 'en'))->assertOk();
    }

    public function test_only_super_admins_can_switch_it(): void
    {
        $islandAdmin = $this->islandAdmin($this->listing->island);
        $this->actingAs($islandAdmin)->get(route('admin.maintenance.edit'))->assertForbidden();
        $this->actingAs($islandAdmin)->put(route('admin.maintenance.update'), ['on' => '1'])->assertForbidden();
        $this->assertFalse(Maintenance::isOn());

        $this->actingAs($this->admin)->put(route('admin.maintenance.update'), ['on' => '1', 'until' => '2020-01-01T10:00'])->assertSessionHasErrors('until');
    }
}
