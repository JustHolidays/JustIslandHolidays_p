<?php

namespace Tests\Feature;

use App\Enums\ListingStatus as S;
use App\Models\Area;
use App\Models\Enquiry;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\LegalPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsListings;
use Tests\Concerns\ChecksAccessibility;
use Tests\TestCase;

/** Every page type, checked with the static WCAG rules in ChecksAccessibility. */
class AccessibilityTest extends TestCase
{
    use BuildsListings, ChecksAccessibility, RefreshDatabase;

    private Island $island;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        $this->seed(LegalPagesSeeder::class);
        $this->island = Island::factory()->create(['slug' => 'menorca', 'name' => ['en' => 'Menorca']]);
        Area::factory()->create(['island_id' => $this->island->id, 'slug' => 'son-bou', 'name' => ['en' => 'Son Bou']]);
        $this->listing = $this->setStatus($this->completeListing(island: $this->island, licenceVerified: true, attributes: [
            'deeplink_url' => 'https://booking.example/1', 'deeplink_status' => 'ok', 'featured_until' => now()->addMonth(),
        ]), S::Live)->load(['island', 'area']);
        Listing::factory()->onIsland($this->island)->live()->titled('Second home')->count(2)->create();
    }

    private function check(string $url, ?User $as = null): void
    {
        $response = $as ? $this->actingAs($as)->get($url) : $this->get($url);
        $response->assertOk();
        $this->assertAccessibleHtml($response->getContent(), $url);
    }

    public function test_public_pages(): void
    {
        foreach (['/en', '/en/menorca', '/en/menorca/son-bou', '/en/search', '/en/search?type[]=villa&sleeps=4', '/en/owners',
            $this->listing->publicUrl('en'), ...array_map(fn ($s) => "/en/p/{$s}", LegalPagesSeeder::SLUGS)] as $url) {
            $this->check($url);
        }
    }

    public function test_sign_in_pages(): void
    {
        foreach (['/account/login', '/account/register', '/account/forgot-password'] as $url) {
            $this->check($url);
        }
    }

    public function test_account_pages(): void
    {
        $owner = $this->listing->owner;
        Enquiry::factory()->for($this->listing)->create();
        $enquiry = Enquiry::sole();

        foreach (['/account', '/account/listings', '/account/listings/create', '/account/enquiries', "/account/enquiries/{$enquiry->id}",
            '/account/clicks', '/account/billing', '/account/security'] as $url) {
            $this->check($url, $owner);
        }
        foreach (['basics', 'location', 'photo', 'link', 'licence', 'review'] as $step) {
            $this->check("/account/listings/{$this->listing->id}/edit/{$step}", $owner);
        }
    }

    public function test_admin_pages(): void
    {
        $super = $this->superAdmin();
        $affiliate = User::factory()->affiliate()->create();
        $page = \App\Models\Page::first();
        $plan = \App\Models\Plan::first();
        $enquiry = Enquiry::factory()->for($this->listing)->create();
        $agent = User::factory()->agent()->create();
        \App\Models\Listing::factory()->live()->onIsland($this->island)->titled('Agent villa')->create(['owner_id' => $agent->id]);

        foreach (['/admin', '/admin/listings', '/admin/listings?status=live', "/admin/listings/{$this->listing->id}", '/admin/listings/import',
            '/admin/licences', '/admin/enquiries', "/admin/enquiries/{$enquiry->id}", '/admin/users',
            "/admin/users/{$this->listing->owner_id}", '/admin/agents', "/admin/agents/{$agent->id}", '/admin/affiliates', '/admin/affiliates/create', "/admin/affiliates/{$affiliate->id}", "/admin/affiliates/{$affiliate->id}/deeplinks",
            '/admin/staff', '/admin/staff/create', "/admin/staff/{$super->id}/edit", '/admin/clicks', '/admin/clicks/events',
            '/admin/islands', "/admin/islands/{$this->island->id}", '/admin/locations/create', "/admin/islands/{$this->island->id}/edit", '/admin/pages', '/admin/pages/create', "/admin/pages/{$page->id}/edit",
            '/admin/property-types', '/admin/property-types/1/edit', '/admin/ads', '/admin/ads/create', '/admin/appearance', '/admin/plans', "/admin/plans/{$plan->id}/edit", '/admin/audit'] as $url) {
            $this->check($url, $super);
        }
    }
}
