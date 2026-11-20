<?php

namespace App\Http\Controllers\Admin;

use App\Billing\AccountPlan;
use App\Enums\Role;
use App\Jobs\ProcessListingImage;
use App\Listings\DisplayLocation;
use App\Listings\ListingWorkflow;
use App\Listings\ListingWorkflowException;
use App\Listings\RankScore;
use App\Models\Area;
use App\Models\Listing;
use App\Models\ListingLicence;
use App\Models\ListingPrivate;
use App\Models\ListingTranslation;
use App\Models\PropertyType;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Add a property for an owner or agent (/admin/listings/add), decided 19 Nov 2026.
 * Island admins for their own islands, super admins for all. The admin fills in everything
 * the owner's editor asks for. If they confirm they have checked the licence number in the
 * official register, the licence is recorded as verified and the property goes live straight
 * away; otherwise it waits in the review queue. The owner or agent is emailed when it goes live.
 */
class PropertyCreateController extends AdminController
{
    public function create(Request $request): View
    {
        $islands = $this->ctx()->islands()->where('is_active', true)->values();

        return view('admin.listings.create', [
            'islands' => $islands,
            'areas' => Area::whereIn('island_id', $islands->pluck('id'))->orderBy('sort_order')->get()->groupBy('island_id'),
            'types' => PropertyType::active(),
            'listerEmail' => (string) $request->query('lister'),
        ]);
    }

    public function store(Request $request, ListingWorkflow $workflow, AccountPlan $plans): RedirectResponse
    {
        $admin = $request->user();
        $data = $request->validate([
            'lister_email' => ['required', 'email'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:500'],
            'property_type' => ['required', Rule::in(PropertyType::activeCodes())],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'sleeps' => ['required', 'integer', 'min:1', 'max:40'],
            // Private pool is a listing column; a shared pool is the 'shared_pool' facility.
            'pool' => ['required', Rule::in(['private', 'shared', 'none'])],
            'pets' => ['required', Rule::in(['yes', 'no', 'not_stated'])],
            'distance_to_beach_m' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'price' => ['required', 'integer', 'min:1', 'max:1000000'],   // a From price is required (19 Nov 2026)
            'currency' => ['required', Rule::in(['EUR', 'GBP'])],
            'coordinates' => ['required', 'string', 'max:60'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:min_width=800,min_height=500'],
            'image_alt' => ['required', 'string', 'max:160'],
            'photo_permission' => ['accepted'],
            'registration_number' => ['nullable', 'string', 'max:60', 'required_without:national_registry_no'],
            'national_registry_no' => ['nullable', 'string', 'max:60'],
            'licence_checked' => ['nullable', 'boolean'],
            'deeplink_url' => ['nullable', 'url:https', 'max:2048'],
            'link_type' => ['nullable', Rule::in(array_keys(Listing::LINK_TYPES))],
        ], [
            'lister_email.required' => 'Enter the email address of the owner or agent this property belongs to.',
            'price.required' => 'Add the From price per week. The property cannot go live without one.',
            'price.min' => 'The From price must be more than 0.',
            'photo.required' => 'Add a photo.',
            'photo.dimensions' => 'The photo must be at least 800 pixels wide and 500 pixels tall.',
            'photo.max' => 'The photo must be 10 MB or smaller.',
            'image_alt.required' => 'Describe the photo in a few words, for people who cannot see it.',
            'photo_permission.accepted' => 'Please confirm the owner or agent owns the photo or has permission to use it.',
            'registration_number.required_without' => 'Enter the Balearic tourist licence number, or the national registry number.',
            'deeplink_url.url' => 'The booking link must be a full web address starting with https://',
        ]);

        $lister = User::where('email', Str::lower($data['lister_email']))->first();
        if (! $lister || ! in_array($lister->role, [Role::Owner, Role::Agent], true)) {
            throw ValidationException::withMessages(['lister_email' => 'There is no owner or agent account with this email. They need to sign up first, or check the spelling.']);
        }
        if ($lister->isDeactivated()) {
            throw ValidationException::withMessages(['lister_email' => 'This account is deactivated, so properties cannot be added to it.']);
        }

        $data['link_type'] ??= 'booking';
        $hasLink = ($data['deeplink_url'] ?? null) !== null;
        if ($hasLink && ($data['link_type'] === 'facebook') !== Listing::isFacebookUrl($data['deeplink_url'])) {
            throw ValidationException::withMessages(['deeplink_url' => $data['link_type'] === 'facebook'
                ? 'This is not a Facebook address. Facebook page addresses start with https://www.facebook.com/'
                : 'This is a Facebook address. Choose "Their Facebook page", or add a different link.']);
        }

        $area = Area::with('island')->findOrFail($data['area_id']);
        $this->ctx()->authorizeIsland($area->island_id);   // island admins: their own islands only
        if (! $area->island->is_active) {
            throw ValidationException::withMessages(['area_id' => 'That island is switched off.']);
        }

        $coords = DisplayLocation::parse($data['coordinates']);
        if (! $coords) {
            throw ValidationException::withMessages(['coordinates' => 'Enter the map coordinates as two numbers on the Balearic Islands, for example 39.9112, 4.2650.']);
        }

        $originalPath = $request->file('photo')->store('listing-originals/admin', 'local');
        $licenceChecked = $request->boolean('licence_checked');

        $listing = DB::transaction(function () use ($data, $area, $coords, $lister, $admin, $plans, $licenceChecked) {
            $listing = new Listing([
                'island_id' => $area->island_id,
                'area_id' => $area->id,
                'property_type' => $data['property_type'],
                'bedrooms' => $data['bedrooms'],
                'bathrooms' => $data['bathrooms'],
                'sleeps' => $data['sleeps'],
                'has_private_pool' => $data['pool'] === 'private',
                'pets_allowed' => ['yes' => true, 'no' => false, 'not_stated' => null][$data['pets']],
                'distance_to_beach_m' => $data['distance_to_beach_m'] ?? null,
                'min_price_week_cents' => isset($data['price']) ? $data['price'] * 100 : null,
                'currency' => $data['currency'],
                'image_alt' => $data['image_alt'],
                'deeplink_url' => $data['deeplink_url'] ?? null,
            ]);
            [$listing->display_lat, $listing->display_lng] = DisplayLocation::offset(...$coords);
            $listing->link_type = ($data['deeplink_url'] ?? null) !== null ? $data['link_type'] : 'booking';
            $listing->owner_id = $lister->id;
            $listing->agency_id = $lister->role === Role::Agent ? $lister->agency_id : null;
            $listing->slug = $this->uniqueSlug($data['title'], $area->island_id);
            $listing->plan_code = $plans->planFor($plans->accountFor($lister))->code;
            $listing->save();

            ListingTranslation::create(['listing_id' => $listing->id, 'lang' => 'en', 'title' => $data['title'], 'summary' => $data['summary']]);

            if ($data['pool'] === 'shared' && ($sharedPool = \App\Models\Amenity::where('code', 'shared_pool')->value('id'))) {
                $listing->amenities()->attach($sharedPool);
            }

            $private = new ListingPrivate(['lat' => $coords[0], 'lng' => $coords[1], 'contact_email' => ($data['contact_email'] ?? null) ?: $lister->email]);
            $private->listing_id = $listing->id;
            $private->save();

            $licence = new ListingLicence([
                'registration_number' => $data['registration_number'] ?? null,
                'national_registry_no' => $data['national_registry_no'] ?? null,
            ]);
            $licence->listing_id = $listing->id;
            $licence->forceFill($licenceChecked
                ? ['status' => 'verified', 'verified_by' => $admin->id, 'verified_at' => now(), 'notes' => 'Checked in the official register when the property was added by an admin.']
                : ['status' => 'pending'])->save();

            Audit::record($admin, 'listing.admin_create', $listing, null, ['lister_id' => $lister->id, 'licence_checked' => $licenceChecked]);
            if ($licenceChecked) {
                Audit::record($admin, 'licence.verify', 'listing_licence', $listing->id, ['registration_number' => $licence->registration_number, 'national_registry_no' => $licence->national_registry_no]);
            }

            return $listing;
        });

        // Synchronous, so the photo exists before the approval below.
        ProcessListingImage::dispatchSync($listing->id, $originalPath);
        $listing->refresh();
        $listing->forceFill(['rank_score' => RankScore::for($listing)])->saveQuietly();

        try {
            $workflow->submitOnBehalf($listing, $admin);
            if ($licenceChecked) {
                $workflow->approve($listing->fresh(), $admin);
            }
        } catch (ListingWorkflowException $e) {
            return redirect()->route('admin.listings.show', $listing)->withErrors([$e->getMessage(), ...$e->problems]);
        }

        $status = $listing->fresh()->status->value;
        $message = match ($status) {
            'live' => "Added for {$lister->name} and live now. They have been emailed.",
            'paused' => "Added for {$lister->name}, but paused: their plan has no room for another live property.",
            default => "Added for {$lister->name}. It is waiting for review, because the licence still needs checking.",
        };

        return redirect()->route('admin.listings.show', $listing)->with('status', $message);
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
