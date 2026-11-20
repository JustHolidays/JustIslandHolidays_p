<?php

namespace Tests\Feature\Database;

use App\Models\Island;
use App\Models\Listing;
use App\Models\Page;
use App\Support\Database\MySql;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Database-level rules that only MySQL enforces. Skipped on SQLite.
 * Run with: composer test:mysql
 */
class MySqlConstraintsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! MySql::isMySql()) {
            $this->markTestSkipped('MySQL-only constraints.');
        }
    }

    private function assertRejected(callable $write, string $why): void
    {
        try {
            $write();
        } catch (QueryException $e) {
            $this->assertSame(3819, $e->errorInfo[1], "{$why}: expected a CHECK constraint failure");

            return;
        }

        $this->fail("Not rejected: {$why}");
    }

    /** The spec's ten, plus chk_cal_dates (booking calendar, 19 Nov 2026) the two rates checks and chk_link_type (20 Nov 2026). */
    public function test_all_check_constraints_exist(): void
    {
        $names = collect(DB::select(
            "SELECT CONSTRAINT_NAME AS n FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK' AND CONSTRAINT_NAME LIKE 'chk\\_%'"
        ))->pluck('n')->sort()->values()->all();

        $this->assertSame([
            'chk_ad_dates', 'chk_ad_weight', 'chk_cal_dates', 'chk_currency', 'chk_deeplink_https', 'chk_enq_adults',
            'chk_enq_dates', 'chk_enq_msg', 'chk_link_type', 'chk_rates_dates', 'chk_rates_price', 'chk_rec_target', 'chk_sleeps', 'chk_sub_payer',
        ], $names);
    }

    public function test_listing_rules(): void
    {
        $listing = Listing::factory()->create();

        $this->assertRejected(fn () => $listing->forceFill(['deeplink_url' => 'http://example.com'])->save(), 'deeplink must be https');
        $this->assertRejected(fn () => $listing->forceFill(['deeplink_url' => null, 'sleeps' => 41])->save(), 'sleeps 1 to 40');
        $this->assertRejected(fn () => $listing->forceFill(['sleeps' => 6, 'currency' => 'USD'])->save(), 'currency EUR or GBP');

        $listing->forceFill(['currency' => 'GBP', 'deeplink_url' => 'https://example.com/villa'])->save();
        $this->assertSame('GBP', $listing->fresh()->currency);
    }

    public function test_site_wide_page_slugs_are_unique(): void
    {
        $island = Island::factory()->create();
        Page::factory()->create(['slug' => 'about', 'island_id' => null]);
        Page::factory()->create(['slug' => 'about', 'island_id' => $island->id]);

        $this->expectException(QueryException::class);
        Page::factory()->create(['slug' => 'about', 'island_id' => null]);
    }
}
