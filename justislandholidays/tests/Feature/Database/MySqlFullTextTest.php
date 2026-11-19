<?php

namespace Tests\Feature\Database;

use App\Models\Listing;
use App\Support\Database\MySql;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * InnoDB full-text indexes only see committed rows, so this test commits its data
 * (DatabaseTruncation) instead of running inside a rolled-back transaction. Skipped on SQLite.
 */
class MySqlFullTextTest extends TestCase
{
    use DatabaseTruncation;

    /** Reference rows that migrations add (the six property types) must survive for later tests. */
    protected $exceptTables = ['migrations', 'property_types'];

    protected function setUp(): void
    {
        parent::setUp();

        if (! MySql::isMySql()) {
            $this->markTestSkipped('MySQL-only full-text index.');
        }
    }

    /** Truncation runs before each test; also clean up after, so committed rows never leak into other tests. */
    protected function tearDown(): void
    {
        if (MySql::isMySql()) {
            $this->truncateTablesForAllConnections();
        }

        parent::tearDown();
    }

    public function test_full_text_search_matches_title_and_summary(): void
    {
        $listing = Listing::factory()->create();
        DB::table('listing_translations')->insert([
            'listing_id' => $listing->id, 'lang' => 'en', 'title' => 'Seaside villa with pool', 'summary' => 'Close to the beach at Son Bou',
        ]);

        $search = fn (string $q) => DB::select(
            'SELECT listing_id FROM listing_translations WHERE MATCH (title, summary) AGAINST (? IN BOOLEAN MODE)', [$q]
        );

        $this->assertCount(1, $search('+seaside'));
        $this->assertCount(1, $search('+beach'));
        $this->assertCount(0, $search('+mountain'));
    }

    public function test_listing_search_finds_words_in_the_summary_and_partial_words(): void
    {
        $listing = Listing::factory()->live()->create();
        DB::table('listing_translations')->insert([
            'listing_id' => $listing->id, 'lang' => 'en', 'title' => 'Casa Sol', 'summary' => 'Quiet home near the harbour',
        ]);

        $run = fn (string $q) => app(\App\Search\ListingSearch::class)
            ->run(\App\Search\SearchFilters::fromRequest(\Illuminate\Http\Request::create('/', 'GET', ['q' => $q])), 'en')
            ->total();

        $this->assertSame(1, $run('harbour'));
        $this->assertSame(1, $run('harb'), 'Partial words match with a trailing wildcard.');
        $this->assertSame(1, $run('quiet harbour'));
        $this->assertSame(0, $run('castle'));
        $this->assertSame(0, $run('+-"()<>~*'), 'Search operators typed by visitors are ignored.');
    }
}
