<?php

namespace App\Admin;

use App\Billing\AccountPlan;
use App\Enums\ListingStatus;
use App\Enums\Role;
use App\Listings\DisplayLocation;
use App\Listings\ListingWorkflow;
use App\Listings\RankScore;
use App\Models\Amenity;
use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use App\Models\ListingLicence;
use App\Models\ListingPrivate;
use App\Models\ListingTranslation;
use App\Models\PropertyType;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Listing CSV import and export for super admins (20 Nov 2026).
 *
 * The same columns go out and come back in. A row with a property reference updates that
 * property; a row without one adds a new property, as a draft, for the lister whose email is
 * given. Photos, facilities, the calendar and rates are not in the file; they stay in the
 * editor. Licence numbers are only set on new properties: changing a verified licence goes
 * through Licence checks. Every import is previewed first and audited.
 */
final class ListingCsv
{
    public const MAX_ROWS = 1000;

    /** Column => what goes in it (also the template's notes). */
    public const COLUMNS = [
        'ref' => 'Property reference, 8 letters and numbers. Leave empty to add a new property.',
        'status' => 'Current status. Shown in exports; ignored when importing.',
        'lister_email' => 'Email of the owner, agent or affiliate account. Needed for new properties; it cannot change an existing property\'s lister.',
        'title' => 'Title in English, up to 160 characters.',
        'summary' => 'Summary in English, up to 500 characters.',
        'island' => 'Island, for example menorca.',
        'resort' => 'Resort or town on that island, for example cala-galdana.',
        'property_type' => 'Property type code, for example villa.',
        'bedrooms' => 'Number, 0 to 30.',
        'bathrooms' => 'Number, 0 to 30.',
        'sleeps' => 'Number, 1 to 40.',
        'pool' => 'private, shared or none.',
        'pets' => 'yes, no or not_stated.',
        'beach_distance_m' => 'Metres to the nearest beach, or empty.',
        'from_price' => 'From price per week, a whole number. Needed for owners and agents.',
        'currency' => 'EUR or GBP.',
        'latitude' => 'Exact position, for example 39.9112. Kept private; visitors see a pin about 300 m away.',
        'longitude' => 'Exact position, for example 3.9634.',
        'link_url' => 'Booking page, website or Facebook address starting with https://, or empty.',
        'link_type' => 'booking, website or facebook (affiliates: booking).',
        'licence_number' => 'Tourist licence number. Only used for new properties.',
    ];

    public function __construct(private AccountPlan $plans, private ListingWorkflow $workflow)
    {
    }

    // --- Export ---------------------------------------------------------------------------

    /** Listings to export, with what the rows need. */
    public function exportQuery(?int $islandId = null, ?string $status = null)
    {
        return Listing::query()
            ->when($islandId, fn ($q) => $q->where('island_id', $islandId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['translations', 'island', 'area', 'owner', 'private', 'licence', 'amenities'])
            ->orderBy('island_id')->orderBy('id');
    }

    /** @return array<string, string|int|null> */
    public function row(Listing $l): array
    {
        $shared = $l->amenities->contains('code', 'shared_pool');

        return [
            'ref' => $l->public_ref,
            'status' => $l->status->value,
            'lister_email' => $l->owner?->email,
            'title' => $l->title('en'),
            'summary' => $l->summary('en'),
            'island' => $l->island?->slug,
            'resort' => $l->area?->slug,
            'property_type' => $l->property_type,
            'bedrooms' => $l->bedrooms,
            'bathrooms' => $l->bathrooms,
            'sleeps' => $l->sleeps,
            'pool' => $l->has_private_pool ? 'private' : ($shared ? 'shared' : 'none'),
            'pets' => $l->pets_allowed === null ? 'not_stated' : ($l->pets_allowed ? 'yes' : 'no'),
            'beach_distance_m' => $l->distance_to_beach_m,
            'from_price' => $l->min_price_week_cents !== null ? intdiv($l->min_price_week_cents, 100) : null,
            'currency' => $l->currency,
            'latitude' => $l->private?->lat,
            'longitude' => $l->private?->lng,
            'link_url' => $l->deeplink_url,
            'link_type' => $l->isAffiliateListing() ? 'booking' : ($l->link_type ?? 'booking'),
            'licence_number' => $l->licence?->registration_number ?? $l->licence?->national_registry_no,
        ];
    }

    /** A spreadsheet can run text starting with = + - @ as a formula: such text is written with a leading '. */
    public static function safeCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($text) ? "'".$text : $text;
    }

    // --- Import ---------------------------------------------------------------------------

    /**
     * Reads a CSV file into rows keyed by column name.
     *
     * @return array{rows: list<array{line: int, values: array<string, string>}>, error: ?string, ignored: list<string>}
     */
    public function read(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);   // Excel's byte order mark
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $firstLine = strtok($raw, "\n");
        $delimiter = substr_count((string) $firstLine, ';') > substr_count((string) $firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $raw);
        rewind($handle);

        $header = fgetcsv($handle, null, $delimiter, '"', '');
        if (! $header) {
            return ['rows' => [], 'error' => 'The file is empty.', 'ignored' => []];
        }
        $header = array_map(fn ($h) => Str::snake(trim((string) $h)), $header);
        $missing = array_diff(['ref', 'title'], $header);
        if ($missing !== []) {
            return ['rows' => [], 'error' => 'The first row must be the column names from the template (missing: '.implode(', ', $missing).').', 'ignored' => []];
        }
        $ignored = array_values(array_diff(array_filter($header), array_keys(self::COLUMNS)));

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $line++;
            if ($cells === [null] || implode('', array_map('trim', array_map('strval', $cells))) === '') {
                continue;   // blank line
            }
            $values = [];
            foreach ($header as $i => $column) {
                if (isset(self::COLUMNS[$column])) {
                    $values[$column] = self::unsafeCell(trim((string) ($cells[$i] ?? '')));
                }
            }
            $rows[] = ['line' => $line, 'values' => $values];
            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);

                return ['rows' => [], 'error' => 'Up to '.self::MAX_ROWS.' rows at a time. Split the file and import each part.', 'ignored' => $ignored];
            }
        }
        fclose($handle);

        return ['rows' => $rows, 'error' => $rows === [] ? 'There are no rows under the column names.' : null, 'ignored' => $ignored];
    }

    /** Undoes safeCell(): a leading ' before = + - @ is dropped again. */
    private static function unsafeCell(string $text): string
    {
        return preg_match("/^'[=+\\-@]/", $text) ? substr($text, 1) : $text;
    }

    /**
     * Checks one row and works out what it would do.
     *
     * @param  array<string, string>  $v
     * @return array{line: int, ref: ?string, title: string, action: string, changes: array<string, array{0: mixed, 1: mixed}>, errors: list<string>, notes: list<string>, data: array<string, mixed>}
     */
    public function check(int $line, array $v): array
    {
        $result = ['line' => $line, 'ref' => ($v['ref'] ?? '') ?: null, 'title' => (string) ($v['title'] ?? ''), 'action' => 'add', 'changes' => [], 'errors' => [], 'notes' => [], 'data' => []];
        $err = function (string $message) use (&$result) {
            $result['errors'][] = $message;
        };

        $listing = null;
        if ($result['ref'] !== null) {
            $listing = Listing::where('public_ref', strtolower($result['ref']))->with(['translations', 'owner', 'private', 'licence', 'amenities', 'island', 'area'])->first();
            if (! $listing) {
                $err('No property has the reference '.$result['ref'].'.');

                return $result;
            }
            if ($listing->status === ListingStatus::Archived) {
                $err('This property is archived and cannot be changed.');

                return $result;
            }
            $result['action'] = 'update';
        }

        // Lister
        $email = Str::lower($v['lister_email'] ?? '');
        if ($listing) {
            $lister = $listing->owner;
            if ($email !== '' && $email !== Str::lower((string) $lister?->email)) {
                $err('The lister cannot be changed by an import (this property belongs to '.$lister?->email.').');
            }
        } else {
            $lister = $email !== '' ? User::where('email', $email)->first() : null;
            if (! $lister || ! in_array($lister->role, [Role::Owner, Role::Agent, Role::Affiliate], true) || $lister->isDeactivated()) {
                $err($email === '' ? 'Give the lister\'s email for a new property.' : "There is no active owner, agent or affiliate with the email {$email}.");
            }
        }
        $isAffiliate = $lister?->role === Role::Affiliate;

        // Text
        $title = trim($v['title'] ?? '');
        $summary = trim($v['summary'] ?? '');
        if ($title === '' || mb_strlen($title) > 160) {
            $err('Title is needed, up to 160 characters.');
        }
        if ($summary === '' || mb_strlen($summary) > 500) {
            $err('Summary is needed, up to 500 characters.');
        }

        // Place
        $island = Island::where('is_active', true)->where(fn ($q) => $q->where('slug', Str::slug($v['island'] ?? ''))->orWhere('slug', Str::lower($v['island'] ?? '')))->first();
        $area = $island ? Area::where('island_id', $island->id)->where('slug', Str::slug($v['resort'] ?? ''))->first() : null;
        if (! $island) {
            $err('Island "'.($v['island'] ?? '').'" is not an active island.');
        } elseif (! $area) {
            $err('Resort "'.($v['resort'] ?? '').'" is not on '.$island->slug.'.');
        }

        // Numbers and choices
        $int = function (string $key, int $min, int $max, bool $required, string $label) use ($v, $err) {
            $raw = $v[$key] ?? '';
            if ($raw === '') {
                if ($required) {
                    $err("{$label} is needed.");
                }

                return null;
            }
            if (! preg_match('/^\d+$/', str_replace([',', ' '], '', $raw)) || (int) str_replace([',', ' '], '', $raw) < $min || (int) str_replace([',', ' '], '', $raw) > $max) {
                $err("{$label} must be a whole number from {$min} to {$max}.");

                return null;
            }

            return (int) str_replace([',', ' '], '', $raw);
        };
        $type = $v['property_type'] ?? '';
        if (! in_array($type, PropertyType::allowedFor($listing?->property_type), true)) {
            $err('Property type "'.$type.'" is not one of the active types.');
        }
        $bedrooms = $int('bedrooms', 0, 30, true, 'Bedrooms');
        $bathrooms = $int('bathrooms', 0, 30, true, 'Bathrooms');
        $sleeps = $int('sleeps', 1, 40, true, 'Sleeps');
        $beach = $int('beach_distance_m', 0, 100000, false, 'Beach distance');
        $price = $int('from_price', 1, 1000000, ! $isAffiliate, 'From price');

        $pool = ['yes' => 'private', 'no' => 'none', '' => 'none'][Str::lower($v['pool'] ?? '')] ?? Str::lower($v['pool'] ?? '');
        if (! in_array($pool, ['private', 'shared', 'none'], true)) {
            $err('Pool must be private, shared or none.');
        }
        $pets = Str::lower($v['pets'] ?? '') ?: 'not_stated';
        if (! in_array($pets, ['yes', 'no', 'not_stated'], true)) {
            $err('Pets must be yes, no or not_stated.');
        }
        $currency = Str::upper($v['currency'] ?? '') ?: 'EUR';
        if (! in_array($currency, ['EUR', 'GBP'], true)) {
            $err('Currency must be EUR or GBP.');
        }

        // Position: needed for new properties; optional on updates (empty keeps it).
        $coords = null;
        if (($v['latitude'] ?? '') !== '' || ($v['longitude'] ?? '') !== '') {
            $coords = DisplayLocation::parse(($v['latitude'] ?? '').', '.($v['longitude'] ?? ''));
            if (! $coords) {
                $err('Latitude and longitude must be a position on the Balearic Islands.');
            }
        } elseif (! $listing) {
            $err('Latitude and longitude are needed for a new property.');
        }

        // Link
        $link = ($v['link_url'] ?? '') ?: null;
        $linkType = $isAffiliate ? 'booking' : (Str::lower($v['link_type'] ?? '') ?: 'booking');
        if ($link !== null && (! filter_var($link, FILTER_VALIDATE_URL) || ! str_starts_with(Str::lower($link), 'https://') || strlen($link) > 2048)) {
            $err('The link must be a full address starting with https://.');
        } elseif (! in_array($linkType, ['booking', 'website', 'facebook'], true)) {
            $err('Link type must be booking, website or facebook.');
        } elseif ($link !== null && ($linkType === 'facebook') !== Listing::isFacebookUrl($link)) {
            $err($linkType === 'facebook' ? 'A Facebook link must be on facebook.com, fb.com or fb.me.' : 'This is a Facebook address: set link_type to facebook.');
        }
        if ($isAffiliate && $link === null) {
            $err('Affiliate properties need a booking link.');
        }

        $licence = trim($v['licence_number'] ?? '') ?: null;
        if ($licence !== null && mb_strlen($licence) > 60) {
            $err('Licence number is up to 60 characters.');
        }

        if ($result['errors'] !== []) {
            return $result;
        }

        $result['data'] = [
            'lister_id' => $lister->id,
            'title' => $title, 'summary' => $summary,
            'island_id' => $island->id, 'area_id' => $area->id,
            'property_type' => $type, 'bedrooms' => $bedrooms, 'bathrooms' => $bathrooms, 'sleeps' => $sleeps,
            'pool' => $pool, 'pets' => $pets, 'distance_to_beach_m' => $beach,
            'min_price_week_cents' => $price !== null ? $price * 100 : null, 'currency' => $currency,
            'coords' => $coords, 'deeplink_url' => $link, 'link_type' => $link === null ? 'booking' : $linkType,
            'licence' => $licence,
        ];

        if ($listing) {
            $before = $this->row($listing);
            $after = [
                'title' => $title, 'summary' => $summary, 'island' => $island->slug, 'resort' => $area->slug,
                'property_type' => $type, 'bedrooms' => $bedrooms, 'bathrooms' => $bathrooms, 'sleeps' => $sleeps,
                'pool' => $pool, 'pets' => $pets, 'beach_distance_m' => $beach,
                'from_price' => $price, 'currency' => $currency,
                'latitude' => $coords[0] ?? $before['latitude'], 'longitude' => $coords[1] ?? $before['longitude'],
                'link_url' => $link, 'link_type' => $link === null ? $before['link_type'] : $result['data']['link_type'],
            ];
            foreach ($after as $key => $new) {
                $old = $before[$key];
                $same = in_array($key, ['latitude', 'longitude'], true)
                    ? abs((float) $old - (float) $new) < 0.000001
                    : (string) ($old ?? '') === (string) ($new ?? '');
                if (! $same) {
                    $result['changes'][$key] = [$old, $new];
                }
            }
            if ($licence !== null && $licence !== (string) $before['licence_number']) {
                $result['notes'][] = 'Licence number not changed: use Licence checks for that.';
            }
            if ($listing->rates()->exists() && isset($result['changes']['from_price'])) {
                $result['notes'][] = 'The From price follows this property\'s rates table, so it may be set back.';
            }
            if ($isAffiliate && isset($result['changes']['link_url']) && $listing->status === ListingStatus::Live) {
                $result['notes'][] = 'The affiliate link changes, so the property goes back for review.';
            }
            $result['action'] = $result['changes'] === [] ? 'unchanged' : 'update';
        } else {
            $result['notes'][] = 'Added as a draft for '.$lister->email.'. They add the photo and send it for review.';
        }

        return $result;
    }

    /** @return Collection<int, array> checked rows */
    public function checkAll(array $rows): Collection
    {
        $seen = [];

        return collect($rows)->map(function (array $row) use (&$seen) {
            $checked = $this->check($row['line'], $row['values']);
            if ($checked['ref'] !== null) {
                $key = strtolower($checked['ref']);
                if (isset($seen[$key])) {
                    $checked['errors'][] = 'This reference is also on line '.$seen[$key].'. Each property once per file.';
                    $checked['action'] = 'add';
                }
                $seen[$key] ??= $row['line'];
            }

            return $checked;
        });
    }

    /**
     * Saves the rows that passed their checks. Rows with errors or no changes are skipped.
     *
     * @return array{added: int, updated: int, skipped: int}
     */
    public function apply(Collection $checked, User $admin): array
    {
        $added = $updated = $skipped = 0;
        foreach ($checked as $row) {
            if ($row['errors'] !== [] || $row['action'] === 'unchanged') {
                $skipped++;

                continue;
            }
            DB::transaction(function () use ($row, $admin, &$added, &$updated) {
                if ($row['action'] === 'update') {
                    $this->update(Listing::where('public_ref', strtolower($row['ref']))->firstOrFail(), $row, $admin);
                    $updated++;
                } else {
                    $this->add($row['data'], $admin);
                    $added++;
                }
            });
        }

        return compact('added', 'updated', 'skipped');
    }

    private function add(array $d, User $admin): Listing
    {
        $lister = User::findOrFail($d['lister_id']);
        $listing = new Listing($this->attributes($d));
        [$listing->display_lat, $listing->display_lng] = DisplayLocation::offset(...$d['coords']);
        $listing->deeplink_url = $d['deeplink_url'];
        $listing->link_type = $d['link_type'];
        $listing->owner_id = $lister->id;
        $listing->agency_id = $lister->role === Role::Agent ? $lister->agency_id : null;
        $listing->slug = $this->uniqueSlug($d['title'], $d['island_id']);
        $listing->plan_code = $lister->role === Role::Affiliate ? 'free' : $this->plans->planFor($this->plans->accountFor($lister))->code;
        $listing->save();

        ListingTranslation::create(['listing_id' => $listing->id, 'lang' => 'en', 'title' => $d['title'], 'summary' => $d['summary']]);
        $this->setSharedPool($listing, $d['pool']);

        $private = new ListingPrivate(['lat' => $d['coords'][0], 'lng' => $d['coords'][1], 'contact_email' => $lister->email]);
        $private->listing_id = $listing->id;
        $private->save();

        if ($lister->role !== Role::Affiliate || $d['licence'] !== null) {
            $licence = new ListingLicence(['registration_number' => $d['licence']]);
            $licence->listing_id = $listing->id;
            $licence->forceFill(['status' => 'pending'])->save();
        }

        $listing->forceFill(['rank_score' => RankScore::for($listing->fresh())])->saveQuietly();
        Audit::record($admin, 'listing.csv_create', $listing, null, ['lister_id' => $lister->id]);

        return $listing;
    }

    private function update(Listing $listing, array $row, User $admin): void
    {
        $d = $row['data'];
        $listing->loadMissing(['private', 'owner']);
        $linkChanged = (string) $listing->deeplink_url !== (string) $d['deeplink_url'];

        $listing->fill($this->attributes($d));
        if ($listing->title('en') !== $d['title'] || $listing->isDirty('island_id')) {
            $listing->slug = $this->uniqueSlug($d['title'], $d['island_id'], $listing->id);
        }
        if ($d['coords'] !== null && (isset($row['changes']['latitude']) || isset($row['changes']['longitude']))) {
            [$listing->display_lat, $listing->display_lng] = DisplayLocation::offset(...$d['coords']);
            $listing->private?->forceFill(['lat' => $d['coords'][0], 'lng' => $d['coords'][1]])->save();
        }
        if ($linkChanged) {
            $listing->forceFill(['deeplink_url' => $d['deeplink_url'], 'deeplink_status' => 'unchecked', 'deeplink_checked_at' => null]);
        }
        if ($d['deeplink_url'] !== null) {
            $listing->link_type = $d['link_type'];
        }
        $listing->save();

        ListingTranslation::query()->updateOrInsert(['listing_id' => $listing->id, 'lang' => 'en'], ['title' => $d['title'], 'summary' => $d['summary'], 'is_machine_translated' => false]);
        $this->setSharedPool($listing, $d['pool']);
        $listing->forceFill(['rank_score' => RankScore::for($listing->fresh())])->saveQuietly();
        Audit::record($admin, 'listing.csv_update', $listing, null, ['changed' => array_keys($row['changes'])]);

        if ($linkChanged && $listing->isAffiliateListing()) {
            $this->workflow->sendBackForReview($listing->fresh(), $admin, 'The Check availability link changed.');
        }
    }

    private function attributes(array $d): array
    {
        return [
            'island_id' => $d['island_id'],
            'area_id' => $d['area_id'],
            'property_type' => $d['property_type'],
            'bedrooms' => $d['bedrooms'],
            'bathrooms' => $d['bathrooms'],
            'sleeps' => $d['sleeps'],
            'has_private_pool' => $d['pool'] === 'private',
            'pets_allowed' => ['yes' => true, 'no' => false, 'not_stated' => null][$d['pets']],
            'distance_to_beach_m' => $d['distance_to_beach_m'],
            'min_price_week_cents' => $d['min_price_week_cents'],
            'currency' => $d['currency'],
        ];
    }

    private function setSharedPool(Listing $listing, string $pool): void
    {
        $shared = Amenity::where('code', 'shared_pool')->value('id');
        if (! $shared) {
            return;
        }
        $pool === 'shared' ? $listing->amenities()->syncWithoutDetaching([$shared]) : $listing->amenities()->detach($shared);
    }

    private function uniqueSlug(string $title, int $islandId, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($title) ?: 'holiday-home', 100, '');
        $slug = $base;
        for ($n = 2; Listing::withTrashed()->where('island_id', $islandId)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
