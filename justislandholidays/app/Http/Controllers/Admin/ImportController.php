<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Jobs\ProcessListingImage;
use App\Listings\DisplayLocation;
use App\Listings\Import\PageParser;
use App\Listings\ListingWorkflow;
use App\Listings\ListingWorkflowException;
use App\Listings\RankScore;
use App\Models\Area;
use App\Models\Listing;
use App\Models\ListingPrivate;
use App\Models\ListingTranslation;
use App\Models\User;
use App\Models\PropertyType;
use App\Support\Audit;
use App\Support\Fetch\FetchException;
use App\Support\Fetch\SafeFetcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Add an affiliate listing from the property's web address. The page is fetched safely,
 * what it offers fills the draft, and the admin completes the rest. The provider's photo is
 * copied to our own storage only after the permission box is ticked; it is never hot-linked.
 * The page address is kept for admins and never shown to visitors. Pets are not asked here.
 */
class ImportController extends AdminController
{
    public function create(): View
    {
        return $this->form([]);
    }

    public function fetch(Request $request, SafeFetcher $fetcher, PageParser $parser): View
    {
        $data = $request->validate([
            'affiliate_id' => ['required', Rule::exists('users', 'id')->where('role', Role::Affiliate->value)->whereNull('deactivated_at')],
            'source_url' => ['required', 'url:https', 'max:2048'],
        ], ['source_url.url' => 'The page address must start with https://']);

        $draft = ['affiliate_id' => (int) $data['affiliate_id'], 'source_url' => $data['source_url']];
        $error = null;

        try {
            $result = $fetcher->get($data['source_url']);
            if (! $result->ok()) {
                $error = "The page answered with error {$result->status}. Fill in the details by hand.";
            } else {
                $parsed = $parser->parse($result->body, $result->url);
                $draft += array_filter($parsed, fn ($v) => $v !== null && $v !== []);
                if ($parsed['lat'] !== null && $parsed['lng'] !== null && DisplayLocation::parse($parsed['lat'].', '.$parsed['lng'])) {
                    $draft['coordinates'] = $parsed['lat'].', '.$parsed['lng'];
                }
                $draft['area_id'] = $this->guessArea($parsed['locality'] ?? null)?->id;
            }
        } catch (FetchException $e) {
            $error = $e->getMessage().' Fill in the details by hand.';
        }

        Audit::record($request->user(), 'import.fetch', 'listing', null, ['url' => $data['source_url'], 'found' => $draft['found'] ?? [], 'error' => $error]);

        return $this->form($draft, $error);
    }

    public function store(Request $request, SafeFetcher $fetcher, ListingWorkflow $workflow): RedirectResponse
    {
        $ctx = $this->ctx();
        $data = $request->validate([
            'affiliate_id' => ['required', Rule::exists('users', 'id')->where('role', Role::Affiliate->value)->whereNull('deactivated_at')],
            'source_url' => ['nullable', 'url:https', 'max:2048'],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:500'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'property_type' => ['required', Rule::in(PropertyType::activeCodes())],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'sleeps' => ['required', 'integer', 'min:1', 'max:40'],
            'has_private_pool' => ['nullable', 'boolean'],
            'price' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'currency' => ['required', Rule::in(['EUR', 'GBP'])],
            'coordinates' => ['required', 'string', 'max:60'],
            'deeplink_url' => ['required', 'url:https', 'max:2048'],
            'deeplink_subid_param' => ['nullable', 'regex:/^[A-Za-z0-9_\-]{1,30}$/'],
            'link_tested' => ['nullable', 'boolean'],
            'image_url' => ['nullable', 'url:https', 'max:2048'],
            'image_permission' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:min_width=800,min_height=500'],
            'image_alt' => ['required', 'string', 'max:160'],
            'save' => ['required', Rule::in(['draft', 'review', 'publish'])],
        ], [
            'deeplink_url.required' => 'Add the affiliate link from the provider.',
            'deeplink_url.url' => 'The affiliate link must start with https://',
            'image_alt.required' => 'Describe the photo for people who cannot see it.',
        ]);

        $area = Area::with('island')->findOrFail($data['area_id']);
        $ctx->authorizeIsland($area->island_id);
        abort_unless($area->island->is_active || $request->user()->isSuperAdmin(), 422);

        $coords = DisplayLocation::parse($data['coordinates']);
        if (! $coords) {
            throw ValidationException::withMessages(['coordinates' => 'Enter the map coordinates as two numbers on the Balearic Islands, for example 39.9112, 4.2650.']);
        }

        $useFetchedImage = ! $request->hasFile('photo') && filled($data['image_url'] ?? null);
        if ($useFetchedImage && ! ($data['image_permission'] ?? false)) {
            throw ValidationException::withMessages(['image_permission' => 'Tick the box to confirm we have permission to use this photo, or upload a different one.']);
        }
        if (! $request->hasFile('photo') && ! $useFetchedImage && $data['save'] !== 'draft') {
            throw ValidationException::withMessages(['photo' => 'Add a photo before sending the listing for review.']);
        }
        if ($data['save'] === 'publish' && ! ($data['link_tested'] ?? false)) {
            throw ValidationException::withMessages(['link_tested' => 'Test the affiliate link and tick the box before publishing.']);
        }

        // Copy the provider's image to our storage (never hot-linked). Done before saving so a bad image stops the import.
        $originalPath = null;
        if ($request->hasFile('photo')) {
            $originalPath = $request->file('photo')->store('listing-originals/import', 'local');
        } elseif ($useFetchedImage) {
            $originalPath = $this->copyImage($fetcher, $data['image_url']);
        }

        $affiliate = User::findOrFail($data['affiliate_id']);

        $listing = DB::transaction(function () use ($data, $area, $coords, $affiliate, $request) {
            $listing = new Listing([
                'island_id' => $area->island_id,
                'area_id' => $area->id,
                'property_type' => $data['property_type'],
                'bedrooms' => $data['bedrooms'],
                'bathrooms' => $data['bathrooms'],
                'sleeps' => $data['sleeps'],
                'has_private_pool' => (bool) ($data['has_private_pool'] ?? false),
                'pets_allowed' => null,   // not asked on the import form
                'min_price_week_cents' => isset($data['price']) ? $data['price'] * 100 : null,
                'currency' => $data['currency'],
                'image_alt' => $data['image_alt'],
                'source_url' => $data['source_url'] ?? null,
                'deeplink_url' => $data['deeplink_url'],
                'deeplink_subid_param' => $data['deeplink_subid_param'] ?? null,
            ]);
            [$listing->display_lat, $listing->display_lng] = DisplayLocation::offset(...$coords);
            $listing->owner_id = $affiliate->id;
            $listing->slug = $this->uniqueSlug($data['title'], $area->island_id);
            $listing->forceFill([
                'plan_code' => 'free',
                'deeplink_status' => ($data['link_tested'] ?? false) ? 'ok' : 'unchecked',
                'deeplink_checked_at' => ($data['link_tested'] ?? false) ? now() : null,
            ])->save();

            ListingTranslation::create(['listing_id' => $listing->id, 'lang' => 'en', 'title' => $data['title'], 'summary' => $data['summary']]);
            $private = new ListingPrivate(['lat' => $coords[0], 'lng' => $coords[1], 'contact_email' => $affiliate->email]);
            $private->listing_id = $listing->id;
            $private->save();

            Audit::record($request->user(), 'listing.import', $listing, null, ['source_url' => $data['source_url'] ?? null, 'affiliate_id' => $affiliate->id]);

            return $listing;
        });

        if ($originalPath) {
            // Synchronous, so the photo exists before any approval below.
            ProcessListingImage::dispatchSync($listing->id, $originalPath);
            $listing->refresh();
        }
        $listing->forceFill(['rank_score' => RankScore::for($listing)])->saveQuietly();

        try {
            if (in_array($data['save'], ['review', 'publish'], true)) {
                $workflow->submitOnBehalf($listing, $request->user());
            }
            if ($data['save'] === 'publish') {
                $workflow->approve($listing->fresh(), $request->user());
            }
        } catch (ListingWorkflowException $e) {
            return redirect()->route('admin.listings.show', $listing)->withErrors([$e->getMessage(), ...$e->problems]);
        }

        $message = ['draft' => 'Saved as a draft.', 'review' => 'Saved and waiting for review.', 'publish' => 'Published. The listing is live.'][$data['save']];

        return redirect()->route('admin.listings.show', $listing)->with('status', $message);
    }

    private function copyImage(SafeFetcher $fetcher, string $url): string
    {
        try {
            $result = $fetcher->get($url, 10_000_000, allowTruncate: false);
        } catch (FetchException $e) {
            throw ValidationException::withMessages(['image_url' => 'The photo could not be copied: '.$e->getMessage().' Upload one instead.']);
        }

        $info = $result->ok() ? @getimagesizefromstring($result->body) : false;
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages(['image_url' => 'That address is not a JPG, PNG or WebP photo. Upload one instead.']);
        }
        if ($info[0] < 800) {
            throw ValidationException::withMessages(['image_url' => 'The provider\'s photo is smaller than 800 pixels wide. Upload a bigger one.']);
        }

        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]];
        $path = 'listing-originals/import/'.Str::uuid().'.'.$ext;
        Storage::disk('local')->put($path, $result->body);

        return $path;
    }

    private function form(array $draft, ?string $error = null): View
    {
        $ctx = $this->ctx();

        return view('admin.import.form', [
            'draft' => $draft,
            'error' => $error,
            'affiliates' => User::where('role', Role::Affiliate)->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
            'islands' => $ctx->islands()->load(['areas' => fn ($q) => $q->orderBy('sort_order')]),
        ]);
    }

    private function guessArea(?string $locality): ?Area
    {
        if (! $locality) {
            return null;
        }

        return $this->ctx()->scope(Area::query())->get()
            ->first(fn (Area $a) => Str::slug($a->translate('name', 'en')) === Str::slug($locality));
    }

    private function uniqueSlug(string $title, int $islandId): string
    {
        $base = Str::limit(Str::slug($title) ?: 'holiday-home', 100, '');
        $slug = $base;
        for ($n = 2; Listing::withTrashed()->where('island_id', $islandId)->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
