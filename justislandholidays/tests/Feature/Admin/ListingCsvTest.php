<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Listing CSV import and export for super admins (20 Nov 2026). */
class ListingCsvTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Island $island;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        $this->admin = $this->superAdmin();
        $this->owner = User::factory()->owner()->create(['email' => 'olivia@owner.example']);
        $this->island = Island::factory()->create(['slug' => 'menorca', 'is_active' => true]);
        $this->area = Area::factory()->create(['island_id' => $this->island->id, 'slug' => 'cala-galdana']);
    }

    /** Builds a CSV file from rows of column => value (columns in template order). */
    private function csv(array $rows, ?array $columns = null): UploadedFile
    {
        $columns ??= array_keys(\App\Admin\ListingCsv::COLUMNS);
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $columns, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($c) => $row[$c] ?? '', $columns), ',', '"', '');
        }
        rewind($out);

        return UploadedFile::fake()->createWithContent('listings.csv', stream_get_contents($out));
    }

    private function newRow(array $overrides = []): array
    {
        return $overrides + [
            'ref' => '', 'lister_email' => 'olivia@owner.example', 'title' => 'Villa Nova', 'summary' => 'Near the beach.',
            'island' => 'menorca', 'resort' => 'cala-galdana', 'property_type' => \App\Models\PropertyType::activeCodes()[0],
            'bedrooms' => '3', 'bathrooms' => '2', 'sleeps' => '6', 'pool' => 'private', 'pets' => 'no',
            'beach_distance_m' => '400', 'from_price' => '1200', 'currency' => 'EUR',
            'latitude' => '39.9361', 'longitude' => '3.9634', 'link_url' => '', 'link_type' => 'booking', 'licence_number' => 'ETV/1',
        ];
    }

    /** Uploads, then returns the preview page and its token. */
    private function upload(UploadedFile $file): array
    {
        $response = $this->actingAs($this->admin)->post(route('admin.listings.csv.upload'), ['file' => $file]);
        $response->assertRedirect();
        $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));

        return [$this->actingAs($this->admin)->get(route('admin.listings.csv.preview', $token))->assertOk(), $token];
    }

    public function test_only_super_admins_can_use_it(): void
    {
        $this->actingAs($this->admin)->get(route('admin.listings.csv'))->assertOk()->assertSee('Import and export listings');
        $this->actingAs($this->admin)->get(route('admin.listings.index'))->assertSee(route('admin.listings.csv'), false);

        $islandAdmin = $this->islandAdmin($this->island);
        $this->actingAs($islandAdmin)->get(route('admin.listings.csv'))->assertForbidden();
        $this->actingAs($islandAdmin)->get(route('admin.listings.csv.export'))->assertForbidden();
        $this->actingAs($islandAdmin)->post(route('admin.listings.csv.upload'), ['file' => $this->csv([$this->newRow()])])->assertForbidden();
        $this->actingAs($islandAdmin)->get(route('admin.listings.index'))->assertDontSee(route('admin.listings.csv'), false);
    }

    public function test_export_has_the_columns_and_one_row_per_listing(): void
    {
        $listing = $this->completeListing($this->owner, $this->island, ['min_price_week_cents' => 95000]);
        $listing->translations()->update(['title' => '=HYPERLINK("x")']);

        $content = $this->actingAs($this->admin)->get(route('admin.listings.csv.export'))->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim(preg_replace('/^\xEF\xBB\xBF/', '', $content)))));

        $this->assertSame(implode(',', array_keys(\App\Admin\ListingCsv::COLUMNS)), trim($lines[0]));
        $this->assertCount(2, $lines);
        $this->assertStringContainsString($listing->public_ref, $lines[1]);
        $this->assertStringContainsString('olivia@owner.example', $lines[1]);
        $this->assertStringContainsString(",950,", $lines[1]);
        $this->assertStringContainsString("'=HYPERLINK", $lines[1], 'Text that looks like a formula is made safe.');
        $this->assertDatabaseHas('audit_log', ['action' => 'listings.csv_export']);

        $template = $this->actingAs($this->admin)->get(route('admin.listings.csv.template'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Villa Example', $template);
    }

    public function test_a_new_row_is_previewed_then_added_as_a_draft(): void
    {
        [$preview, $token] = $this->upload($this->csv([$this->newRow()]));
        $preview->assertSee('nothing has been saved yet')->assertSee('New draft')->assertSee('data-count="add">1<', false);
        $this->assertSame(0, Listing::count(), 'Nothing saved by the preview.');

        $this->actingAs($this->admin)->post(route('admin.listings.csv.apply', $token))->assertRedirect(route('admin.listings.csv'));

        $listing = Listing::with(['private', 'licence', 'translations'])->sole();
        $this->assertSame('Villa Nova', $listing->title('en'));
        $this->assertSame(ListingStatus::Draft, $listing->status);
        $this->assertSame($this->owner->id, $listing->owner_id);
        $this->assertSame(120000, $listing->min_price_week_cents);
        $this->assertTrue($listing->has_private_pool);
        $this->assertEqualsWithDelta(39.9361, (float) $listing->private->lat, 0.00001);
        $this->assertNotEquals((float) $listing->private->lat, (float) $listing->display_lat, 'Visitors see an offset pin.');
        $this->assertSame('ETV/1', $listing->licence->registration_number);
        $this->assertSame('pending', $listing->licence->status);
        $this->assertDatabaseHas('audit_log', ['action' => 'listing.csv_create']);
        $this->assertDatabaseHas('audit_log', ['action' => 'listings.csv_import']);

        // The same preview cannot be saved twice.
        $this->actingAs($this->admin)->post(route('admin.listings.csv.apply', $token))->assertSessionHasErrors('file');
        $this->assertSame(1, Listing::count());
    }

    public function test_an_exported_file_updates_only_what_changed(): void
    {
        $listing = $this->setStatus($this->completeListing($this->owner, $this->island), ListingStatus::Live);
        $content = $this->actingAs($this->admin)->get(route('admin.listings.csv.export'))->streamedContent();
        $edited = str_replace(','.$listing->bedrooms.','.$listing->bathrooms.',', ',5,'.$listing->bathrooms.',', $content);
        $edited = str_replace($listing->title('en'), 'Villa Renamed', $edited);

        [$preview, $token] = $this->upload(UploadedFile::fake()->createWithContent('edited.csv', $edited));
        $preview->assertSee('Update')->assertSee('Villa Renamed')->assertSee('data-count="update">1<', false);

        $this->actingAs($this->admin)->post(route('admin.listings.csv.apply', $token));
        $fresh = $listing->fresh();
        $this->assertSame(5, $fresh->bedrooms);
        $this->assertSame('Villa Renamed', $fresh->title('en'));
        $this->assertSame(ListingStatus::Live, $fresh->status, 'An owner property stays live.');

        // Uploading the unchanged export again changes nothing.
        $again = $this->actingAs($this->admin)->get(route('admin.listings.csv.export'))->streamedContent();
        [$preview2] = $this->upload(UploadedFile::fake()->createWithContent('same.csv', $again));
        $preview2->assertSee('data-count="unchanged">1<', false)->assertSee('There is nothing to save');
    }

    public function test_problem_rows_are_shown_and_skipped(): void
    {
        $rows = [
            $this->newRow(['lister_email' => 'nobody@example.com']),
            $this->newRow(['island' => 'atlantis']),
            $this->newRow(['bedrooms' => 'three', 'latitude' => '51.5', 'longitude' => '-0.1']),
            $this->newRow(['ref' => 'zzzzzzzz']),
            $this->newRow(['link_url' => 'https://www.facebook.com/villa', 'link_type' => 'website']),
            $this->newRow(['title' => 'Good one']),
        ];
        [$preview, $token] = $this->upload($this->csv($rows));
        $preview->assertSee('There is no active owner, agent or affiliate with the email nobody@example.com.')
            ->assertSee('is not an active island')
            ->assertSee('Bedrooms must be a whole number')
            ->assertSee('position on the Balearic Islands')
            ->assertSee('No property has the reference zzzzzzzz.')
            ->assertSee('set link_type to facebook')
            ->assertSee('data-count="errors">5<', false)
            ->assertSee('data-count="add">1<', false);

        $this->actingAs($this->admin)->post(route('admin.listings.csv.apply', $token));
        $this->assertSame(['Good one'], Listing::with('translations')->get()->map->title('en')->all());
    }

    public function test_bad_files_are_refused(): void
    {
        $this->actingAs($this->admin)->from(route('admin.listings.csv'))
            ->post(route('admin.listings.csv.upload'), ['file' => UploadedFile::fake()->createWithContent('x.csv', "name,colour\nA,B\n")])
            ->assertSessionHasErrors('file');
        $this->actingAs($this->admin)->post(route('admin.listings.csv.upload'), ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file');
        $this->actingAs($this->admin)->get(route('admin.listings.csv.preview', str_repeat('a', 32)))->assertRedirect(route('admin.listings.csv'));
    }

    public function test_semicolons_and_excel_byte_order_mark_are_understood(): void
    {
        $columns = array_keys(\App\Admin\ListingCsv::COLUMNS);
        $row = $this->newRow(['title' => 'Villa Molí']);
        $content = "\xEF\xBB\xBF".implode(';', $columns)."\n".implode(';', array_map(fn ($c) => $row[$c] ?? '', $columns))."\n";

        [$preview, $token] = $this->upload(UploadedFile::fake()->createWithContent('excel.csv', $content));
        $preview->assertSee('data-count="add">1<', false);
        $this->actingAs($this->admin)->post(route('admin.listings.csv.apply', $token));
        $this->assertSame('Villa Molí', Listing::sole()->title('en'));
    }
}
