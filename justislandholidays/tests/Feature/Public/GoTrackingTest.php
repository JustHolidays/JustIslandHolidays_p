<?php

namespace Tests\Feature\Public;

use App\Enums\ListingStatus;
use App\Models\DeeplinkClick;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130 Safari/537.36';

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $affiliate = User::factory()->affiliate()->create();
        $this->listing = Listing::factory()->live()
            ->withDeeplink('https://booking.example.com/home/1?ref=partner#top', 'subid')
            ->create(['owner_id' => $affiliate->id])
            ->load(['island', 'area']);
    }

    private function go(array $query = ['s' => 'listing', 'l' => 'en'], string $ua = self::BROWSER, array $headers = [], string $ip = '198.51.100.20'): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders(['User-Agent' => $ua] + $headers)
            ->get('/go/'.$this->listing->public_ref.'?'.http_build_query($query));
    }

    public function test_click_is_recorded_and_visitor_redirected_with_click_id(): void
    {
        $response = $this->go();

        $click = DeeplinkClick::sole();
        $response->assertStatus(302)
            ->assertRedirect('https://booking.example.com/home/1?ref=partner&subid='.$click->click_id.'#top')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertSame(26, strlen($click->click_id));
        $this->assertSame($this->listing->id, $click->listing_id);
        $this->assertSame($this->listing->owner_id, $click->affiliate_id);
        $this->assertSame($this->listing->island_id, $click->island_id);
        $this->assertSame('listing', $click->source_page);
        $this->assertSame('en', $click->lang);
        $this->assertSame('desktop', $click->device);
        $this->assertFalse($click->is_bot);
        $this->assertTrue($click->is_unique);
        $this->assertSame($response->headers->get('Location'), $click->target_url);
    }

    public function test_no_cookies_are_set(): void
    {
        $response = $this->go();

        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_raw_ip_is_never_stored(): void
    {
        $this->go(ip: '198.51.100.99');

        $click = DeeplinkClick::sole();
        $this->assertSame(64, strlen($click->getRawOriginal('ip_hash')));
        $this->assertStringNotContainsString('198.51.100.99', json_encode($click->getAttributes()));
    }

    public function test_link_without_sub_id_param_is_left_unchanged(): void
    {
        $this->listing->forceFill(['deeplink_subid_param' => null, 'deeplink_url' => 'https://booking.example.com/x'])->save();

        $this->go()->assertRedirect('https://booking.example.com/x');
    }

    public function test_repeat_click_within_30_minutes_is_not_unique(): void
    {
        $this->go();
        $this->go();
        $this->travel(31)->minutes();
        $this->go();

        $this->assertSame([true, false, true], DeeplinkClick::orderBy('id')->pluck('is_unique')->all());
    }

    public function test_bots_and_prefetches_are_flagged(): void
    {
        $this->go(ua: 'Googlebot/2.1 (+http://www.google.com/bot.html)');
        $this->go(ua: '');
        $this->go(headers: ['Sec-Purpose' => 'prefetch']);

        $this->assertSame(3, DeeplinkClick::where('is_bot', true)->where('is_unique', false)->count());
    }

    public function test_too_many_clicks_from_one_visitor_are_flagged_as_bots(): void
    {
        config(['site.clicks.per_ip_per_minute' => 3]);

        foreach (range(1, 5) as $i) {
            $this->go()->assertStatus(302);
        }

        $this->assertSame(2, DeeplinkClick::where('is_bot', true)->count());
    }

    public function test_device_and_unknown_source_and_language(): void
    {
        $this->go(['s' => 'nonsense', 'l' => 'xx'], 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148');

        $click = DeeplinkClick::sole();
        $this->assertSame('other', $click->source_page);
        $this->assertSame('en', $click->lang);
        $this->assertSame('mobile', $click->device);
    }

    public function test_switched_off_link_goes_to_the_listing_page_without_a_click(): void
    {
        $this->listing->forceFill(['deeplink_status' => 'broken'])->save();

        $this->go()->assertRedirect($this->listing->publicUrl('en'));
        $this->assertSame(0, DeeplinkClick::count());
    }

    public function test_listing_without_a_link_goes_to_the_listing_page(): void
    {
        $this->listing->forceFill(['deeplink_url' => null])->save();

        $this->go()->assertRedirect($this->listing->publicUrl('en'));
        $this->assertSame(0, DeeplinkClick::count());
    }

    public function test_not_live_or_unknown_listing_returns_404(): void
    {
        $this->listing->forceFill(['status' => ListingStatus::Paused])->save();
        $this->go()->assertNotFound();

        $this->get('/go/zzzzzzzz')->assertNotFound();
        $this->assertSame(0, DeeplinkClick::count());
    }
}
