<?php

namespace Tests\Feature\Public;

use App\Models\Island;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The Our partners page: active affiliates, logos linking (through a click counter) to their home page. */
class PartnersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_links_to_the_page_and_it_lists_active_affiliates_only(): void
    {
        $sunny = User::factory()->affiliate()->create(['name' => 'Sunny Stays', 'affiliate_website_url' => 'https://sunny.example/']);
        $bay = User::factory()->affiliate()->create(['name' => 'Bay Villas']);
        User::factory()->affiliate()->create(['name' => 'Closed Co', 'deactivated_at' => now()]);
        User::factory()->owner()->create(['name' => 'Olive Owner']);

        $this->get('/en')->assertOk()->assertSee('href="'.route('partners', ['lang' => 'en']).'"', false)->assertSee('Our partners');

        $html = $this->get('/en/partners')->assertOk()->assertSee('<h1 class="text-3xl font-semibold">Our partners</h1>', false)->getContent();
        $this->assertStringContainsString('Sunny Stays', $html);
        $this->assertStringContainsString('Bay Villas', $html);
        $this->assertStringNotContainsString('Closed Co', $html);
        $this->assertStringNotContainsString('Olive Owner', $html);
        $this->assertLessThan(strpos($html, 'Sunny Stays'), strpos($html, 'Bay Villas'), 'In name order.');

        // Sunny's logo links through the counter; Bay has no website, so no link.
        $this->assertStringContainsString('href="'.route('partners.go', $sunny->id).'" rel="sponsored nofollow noopener" target="_blank"', $html);
        $this->assertStringNotContainsString(route('partners.go', $bay->id), $html);
        $this->assertStringContainsString('images/placeholders/partner-logo.webp', $html, 'Placeholder until a logo is added.');
    }

    public function test_a_logo_click_is_counted_and_goes_to_their_home_page(): void
    {
        $sunny = User::factory()->affiliate()->create(['affiliate_website_url' => 'https://sunny.example/']);

        $this->get(route('partners.go', $sunny->id))->assertRedirect('https://sunny.example/')->assertHeaderMissing('Set-Cookie');
        $this->get(route('partners.go', $sunny->id));

        $this->assertSame(2, (int) DB::table('partner_clicks_daily')->where('affiliate_id', $sunny->id)->where('day', now()->toDateString())->value('clicks'));
    }

    public function test_no_website_inactive_or_not_an_affiliate_goes_back_to_the_page(): void
    {
        $noSite = User::factory()->affiliate()->create();
        $closed = User::factory()->affiliate()->create(['affiliate_website_url' => 'https://closed.example/', 'deactivated_at' => now()]);
        $owner = User::factory()->owner()->create();

        foreach ([$noSite->id, $closed->id, $owner->id, 999999] as $id) {
            $this->get(route('partners.go', $id))->assertRedirect(route('partners', ['lang' => 'en']));
        }
        $this->assertSame(0, DB::table('partner_clicks_daily')->count());
    }

    public function test_super_admin_sets_the_website_and_sees_the_clicks(): void
    {
        $sunny = User::factory()->affiliate()->create(['name' => 'Sunny Stays']);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('admin.affiliates.website', $sunny), ['website' => 'https://sunny.example/'])->assertSessionHasNoErrors();
        $this->assertSame('https://sunny.example/', $sunny->fresh()->affiliate_website_url);
        $this->assertDatabaseHas('audit_log', ['action' => 'affiliate.website_update']);

        $this->get(route('partners.go', $sunny->id));
        $this->actingAs($admin)->get(route('admin.affiliates.show', $sunny))->assertOk()
            ->assertSee('Logo clicks: 1 in the last 30 days, 1 in total.');

        $this->actingAs($admin)->post(route('admin.affiliates.website', $sunny), ['website' => 'http://insecure.example'])->assertSessionHasErrors('website');
        $this->actingAs($admin)->post(route('admin.affiliates.website', $sunny), ['website' => ''])->assertSessionHasNoErrors();
        $this->assertNull($sunny->fresh()->affiliate_website_url);
    }

    public function test_only_super_admins_set_the_website(): void
    {
        $sunny = User::factory()->affiliate()->create();

        $this->actingAs($this->islandAdmin(Island::factory()->create()))->post(route('admin.affiliates.website', $sunny), ['website' => 'https://x.example/'])->assertForbidden();
        $this->actingAs($sunny)->post(route('admin.affiliates.website', $sunny), ['website' => 'https://x.example/'])->assertForbidden();
        $this->assertNull($sunny->fresh()->affiliate_website_url);
    }
}
