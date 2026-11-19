<?php

namespace App\Http\Controllers\Account;

use App\Billing\AccountPlan;
use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessListingImage;
use App\Listings\DisplayLocation;
use App\Listings\ListingChecklist;
use App\Listings\ListingWorkflow;
use App\Listings\ListingWorkflowException;
use App\Listings\RankScore;
use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use App\Models\ListingLicence;
use App\Models\ListingPrivate;
use App\Models\ListingTranslation;
use App\Models\PropertyType;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The lister's step-by-step listing editor: basics, location, photo, link, licence, review.
 * Status changes go through ListingWorkflow. Admin-only fields are never accepted here.
 */
class ListingEditorController extends Controller
{
    public function __construct(private ListingWorkflow $workflow, private AccountPlan $plans)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $listings = Listing::query()
            ->where(fn ($q) => $q->where('owner_id', $user->id)
                ->when($user->agency_id, fn ($q) => $q->orWhere('agency_id', $user->agency_id)))
            ->with(['island', 'area', 'translations', 'licence'])
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 WHEN 'rejected' THEN 1 WHEN 'pending_review' THEN 2 WHEN 'live' THEN 3 WHEN 'paused' THEN 4 ELSE 5 END")
            ->latest('updated_at')
            ->get();

        return view('account.listings.index', [
            'listings' => $listings,
            'plan' => $user->can('use-billing') ? $this->plans->planFor($this->plans->accountFor($user)) : null,
        ]);
    }

    public function create(Request $request): View
    {
        return view('account.listings.edit', $this->editorData($request, new Listing(), 'basics'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateBasics($request, null, $request->user()->isAffiliate());
        $user = $request->user();
        $island = Island::findOrFail($data['island_id']);

        $listing = DB::transaction(function () use ($data, $user, $island) {
            $listing = new Listing($this->basicsAttributes($data));
            $listing->owner_id = $user->id;
            $listing->agency_id = $user->role?->value === 'agent' ? $user->agency_id : null;
            $listing->slug = $this->uniqueSlug($data['title'], $island->id);
            // Placeholder pin at the island centre until the location step is saved.
            $listing->display_lat = $island->centre_lat ?? 39.6;
            $listing->display_lng = $island->centre_lng ?? 3.0;
            $listing->plan_code = $user->isAffiliate() ? 'free' : $this->plans->planFor($this->plans->accountFor($user))->code;
            $listing->save();

            $this->saveEnglishText($listing, $data);
            Audit::record($user, 'listing.create', $listing);

            return $listing;
        });

        return redirect()->route('account.listings.edit', [$listing, 'location'])->with('status', 'Saved. Next, add the location.');
    }

    public function edit(Request $request, Listing $listing, string $step = 'basics'): View
    {
        if ($step === 'licence' && $listing->isAffiliateListing()) {
            abort(404);
        }

        return view('account.listings.edit', $this->editorData($request, $listing, $step));
    }

    public function updateBasics(Request $request, Listing $listing): RedirectResponse
    {
        $this->ensureEditable($listing);
        $data = $this->validateBasics($request, $listing->property_type, $listing->isAffiliateListing());

        DB::transaction(function () use ($listing, $data, $request) {
            $listing->fill($this->basicsAttributes($data));
            $currentTitle = $listing->title('en');
            if ($currentTitle !== $data['title'] || $listing->isDirty('island_id')) {
                $listing->slug = $this->uniqueSlug($data['title'], (int) $data['island_id'], $listing->id);
            }
            $listing->save();
            $this->saveEnglishText($listing, $data);
            $this->refreshRank($listing);
            Audit::record($request->user(), 'listing.update', $listing, null, ['step' => 'basics']);
        });

        return $this->next($listing, 'location');
    }

    public function updateLocation(Request $request, Listing $listing): RedirectResponse
    {
        $this->ensureEditable($listing);
        $data = $request->validate([
            'coordinates' => ['required', 'string', 'max:60'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['required', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +()\-]{6,40}$/'],
            'website_url' => ['nullable', 'url:https', 'max:255'],
            'show_phone' => ['nullable', 'boolean'],   // only asked of owners and agents; hidden unless they say yes
        ], [
            'website_url.url' => 'The website must start with https://',
            'contact_phone.regex' => 'Enter the phone number using digits, spaces and + only.',
        ]);

        $coordinates = DisplayLocation::parse($data['coordinates']);
        if ($coordinates === null) {
            throw ValidationException::withMessages([
                'coordinates' => 'Enter the map coordinates as two numbers, for example 39.9112, 4.2650. They must be on the Balearic Islands.',
            ]);
        }
        [$lat, $lng] = $coordinates;

        DB::transaction(function () use ($listing, $data, $lat, $lng, $request) {
            $private = $listing->private ?? tap(new ListingPrivate(), fn ($p) => $p->listing_id = $listing->id);
            $moved = $private->lat === null || (float) $private->lat !== $lat || (float) $private->lng !== $lng;

            $private->fill(['lat' => $lat, 'lng' => $lng] + collect($data)->except('coordinates')->all())->save();

            if ($moved) {
                [$listing->display_lat, $listing->display_lng] = DisplayLocation::offset($lat, $lng);
                $listing->save();
            }
            Audit::record($request->user(), 'listing.update', $listing, null, ['step' => 'location']);
        });

        return $this->next($listing, 'photo');
    }

    public function updatePhoto(Request $request, Listing $listing): RedirectResponse
    {
        $this->ensureEditable($listing);
        $request->validate([
            'photo' => [Rule::requiredIf(! $listing->image_path), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:min_width=800,min_height=500'],
            'image_alt' => ['required', 'string', 'max:160'],
            'photo_permission' => [Rule::requiredIf($request->hasFile('photo')), 'nullable', 'accepted'],
        ], [
            'photo.dimensions' => 'The photo must be at least 800 pixels wide and 500 pixels tall.',
            'photo.max' => 'The photo must be 10 MB or smaller.',
            'image_alt.required' => 'Describe the photo in a few words, for people who cannot see it.',
            'photo_permission.required' => 'Please confirm you own the photo or have permission to use it.',
        ]);

        $listing->image_alt = $request->input('image_alt');
        $listing->save();

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store("listing-originals/{$listing->id}", 'local');
            ProcessListingImage::dispatch($listing->id, $path);
        }

        Audit::record($request->user(), 'listing.update', $listing, null, ['step' => 'photo', 'new_photo' => $request->hasFile('photo')]);

        return $this->next($listing, 'link');
    }

    /** Another photo after the main one, up to the plan's limit (Free 3 in all, paid plans 12). Shows straight away. */
    public function addPhoto(Request $request, Listing $listing): RedirectResponse
    {
        $this->ensureEditable($listing);
        abort_if($listing->isAffiliateListing(), 404);   // affiliate listings have one photo

        if (! $listing->image_path) {
            return back()->withErrors(['extra_photo' => 'Add your main photo first.']);
        }
        $limit = $listing->photoLimit();
        if ($listing->photos()->count() + 1 >= $limit) {
            return back()->withErrors(['extra_photo' => "Your plan allows {$limit} photos, and you have them all. Remove one, or choose a plan with more photos."]);
        }

        $request->validate([
            'extra_photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:min_width=800,min_height=500'],
            'extra_alt' => ['required', 'string', 'max:160'],
            'extra_permission' => ['accepted'],
        ], [
            'extra_photo.required' => 'Choose a photo.',
            'extra_photo.dimensions' => 'The photo must be at least 800 pixels wide and 500 pixels tall.',
            'extra_photo.max' => 'The photo must be 10 MB or smaller.',
            'extra_alt.required' => 'Describe the photo in a few words, for people who cannot see it.',
            'extra_permission.accepted' => 'Please confirm you own the photo or have permission to use it.',
        ]);

        $photo = new \App\Models\ListingPhoto(['alt' => trim((string) $request->input('extra_alt')), 'sort_order' => (int) $listing->photos()->max('sort_order') + 1]);
        $photo->listing_id = $listing->id;
        $photo->save();

        $path = $request->file('extra_photo')->store("listing-originals/{$listing->id}", 'local');
        ProcessListingImage::dispatch($listing->id, $path, $photo->id);
        Audit::record($request->user(), 'listing.update', $listing, null, ['step' => 'photo', 'extra_photo_added' => $photo->id]);

        return back()->with('status', 'Photo added. It may take a minute to appear.');
    }

    public function removePhoto(Request $request, Listing $listing, int $photo): RedirectResponse
    {
        $this->ensureEditable($listing);
        $record = $listing->photos()->whereKey($photo)->firstOrFail();
        $record->deleteFiles();
        $record->delete();
        Audit::record($request->user(), 'listing.update', $listing, null, ['step' => 'photo', 'extra_photo_removed' => $photo]);

        return back()->with('status', 'Photo removed.');
    }

    public function updateLink(Request $request, Listing $listing): RedirectResponse
    {
        $this->ensureEditable($listing);
        $isAffiliate = $listing->isAffiliateListing();

        $data = $request->validate([
            'deeplink_url' => [$isAffiliate ? 'required' : 'nullable', 'url:https', 'max:2048'],
            'deeplink_subid_param' => ['nullable', Rule::prohibitedIf(! $isAffiliate), 'regex:/^[A-Za-z0-9_\-]{1,30}$/'],
        ], [
            'deeplink_url.url' => 'The link must be a full web address starting with https://',
            'deeplink_url.required' => 'Add the booking link for this property.',
        ]);

        $newUrl = $data['deeplink_url'] ?? null;

        DB::transaction(function () use ($listing, $newUrl, $data, $isAffiliate, $request) {
            $changed = $newUrl !== $listing->deeplink_url;

            if ($changed) {
                $listing->forceFill([
                    'deeplink_url' => $newUrl,
                    'deeplink_status' => 'unchecked',
                    'deeplink_checked_at' => null,
                ]);
            }
            if ($isAffiliate) {
                $listing->deeplink_subid_param = $data['deeplink_subid_param'] ?? null;
            }
            $listing->save();
            $this->refreshRank($listing);

            Audit::record($request->user(), 'listing.update', $listing, null, ['step' => 'link', 'changed' => $changed]);

            // Affiliate links are checked, so a new one goes back for review. Owners and agents
            // look after their own links (decided 19 Nov 2026): their change goes live straight away.
            if ($changed && $isAffiliate) {
                $this->workflow->sendBackForReview($listing, $request->user(), 'The Check availability link changed.');
            }
        });

        return $this->next($listing, $isAffiliate ? 'review' : 'licence');
    }

    public function updateLicence(Request $request, Listing $listing): RedirectResponse
    {
        abort_if($listing->isAffiliateListing(), 404);
        $this->ensureEditable($listing);

        $data = $request->validate([
            'registration_number' => ['nullable', 'string', 'max:60', 'required_without:national_registry_no'],
            'national_registry_no' => ['nullable', 'string', 'max:60'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'responsibility' => ['accepted'],
        ], [
            'registration_number.required_without' => 'Enter your Balearic tourist licence number, or the national registry number.',
            'responsibility.accepted' => 'Please confirm that you hold a valid licence for this property.',
        ]);

        DB::transaction(function () use ($listing, $data, $request) {
            $licence = $listing->licence ?? tap(new ListingLicence(), fn ($l) => $l->listing_id = $listing->id);
            $numbersChanged = ! $licence->exists
                || $licence->registration_number !== ($data['registration_number'] ?? null)
                || $licence->national_registry_no !== ($data['national_registry_no'] ?? null);

            $licence->fill([
                'registration_number' => $data['registration_number'] ?? null,
                'national_registry_no' => $data['national_registry_no'] ?? null,
            ]);

            if ($request->hasFile('document')) {
                if ($licence->document_path) {
                    Storage::disk('local')->delete($licence->document_path);
                }
                $licence->document_path = $request->file('document')->store("licences/{$listing->id}", 'local');
            }

            if ($numbersChanged) {
                // A new or changed number needs a fresh manual check by an admin.
                $licence->forceFill(['status' => 'pending', 'verified_by' => null, 'verified_at' => null]);
            }
            $licence->save();

            Audit::record($request->user(), 'licence.submit', $listing, null, ['changed' => $numbersChanged]);

            if ($numbersChanged) {
                $this->workflow->sendBackForReview($listing, $request->user(), 'The licence number changed.');
            }
        });

        return $this->next($listing, 'review');
    }

    public function licenceDocument(Request $request, Listing $listing): StreamedResponse
    {
        $licence = $listing->licence;
        abort_unless($licence?->document_path && $request->user()->can('viewDocument', $licence), 404);

        return Storage::disk('local')->download($licence->document_path);
    }

    public function changeStatus(Request $request, Listing $listing, string $action): RedirectResponse
    {
        $user = $request->user();

        try {
            match ($action) {
                'submit' => $this->workflow->submit($listing, $user),
                'reopen' => $this->workflow->reopen($listing, $user),
                'pause' => $this->workflow->pause($listing, $user),
                'resume' => $this->workflow->resume($listing, $user),
                'archive' => $this->workflow->archive($listing, $user),
            };
        } catch (ListingWorkflowException $e) {
            return back()->withErrors(array_merge([$e->getMessage()], $e->problems));
        }

        $messages = [
            'submit' => 'Sent for review. We aim to check new listings within 48 hours.',
            'reopen' => 'Your listing is a draft again. Make your changes, then send it for review.',
            'pause' => 'Your listing is paused and hidden from visitors.',
            'resume' => 'Your listing is live again.',
            'archive' => 'Your listing is archived.',
        ];

        return redirect()->route('account.listings.index')->with('status', $messages[$action]);
    }

    /** Listings waiting for review are locked so the admin checks what will go live. */
    private function ensureEditable(Listing $listing): void
    {
        if (in_array($listing->status, [ListingStatus::PendingReview, ListingStatus::Archived], true)) {
            throw ValidationException::withMessages([
                'status' => $listing->status === ListingStatus::Archived
                    ? 'This listing is archived and can no longer be edited.'
                    : 'This listing is waiting for review, so it cannot be changed until we have checked it.',
            ]);
        }
    }

    /** @param  string|null  $currentType  a switched-off type the listing already has stays allowed */
    private function validateBasics(Request $request, ?string $currentType = null, bool $isAffiliate = false): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:500'],
            'island_id' => ['required', 'integer', Rule::exists('islands', 'id')->where('is_active', true)],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('island_id', $request->integer('island_id'))],
            'property_type' => ['required', Rule::in(\App\Models\PropertyType::allowedFor($currentType))],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'sleeps' => ['required', 'integer', 'min:1', 'max:40'],
            'has_private_pool' => ['nullable', 'boolean'],
            'pets' => ['required', Rule::in(['yes', 'no', 'not_stated'])],
            'distance_to_beach_m' => ['nullable', 'integer', 'min:0', 'max:100000'],
            // Owners and agents must give a From price (19 Nov 2026); affiliates may leave it out.
            'price' => [$isAffiliate ? 'nullable' : 'required', 'integer', 'min:1', 'max:1000000'],
            'currency' => ['required', Rule::in(['EUR', 'GBP'])],
        ], [
            'area_id.exists' => 'Choose a resort or town on the island you picked.',
            'price.required' => 'Add your From price per week. Your listing cannot go live without one.',
            'price.min' => 'The From price must be more than 0.',
        ]);
    }

    private function basicsAttributes(array $data): array
    {
        return [
            'island_id' => $data['island_id'],
            'area_id' => $data['area_id'],
            'property_type' => $data['property_type'],
            'bedrooms' => $data['bedrooms'],
            'bathrooms' => $data['bathrooms'],
            'sleeps' => $data['sleeps'],
            'has_private_pool' => (bool) ($data['has_private_pool'] ?? false),
            'pets_allowed' => ['yes' => true, 'no' => false, 'not_stated' => null][$data['pets']],
            'distance_to_beach_m' => $data['distance_to_beach_m'] ?? null,
            'min_price_week_cents' => isset($data['price']) ? $data['price'] * 100 : null,
            'currency' => $data['currency'],
        ];
    }

    private function saveEnglishText(Listing $listing, array $data): void
    {
        ListingTranslation::query()->updateOrInsert(
            ['listing_id' => $listing->id, 'lang' => 'en'],
            ['title' => $data['title'], 'summary' => $data['summary'], 'is_machine_translated' => false],
        );
        $listing->unsetRelation('translations');
    }

    private function uniqueSlug(string $title, int $islandId, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($title) ?: 'holiday-home', 100, '');
        $slug = $base;
        $n = 2;

        while (Listing::withTrashed()->where('island_id', $islandId)->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    private function refreshRank(Listing $listing): void
    {
        $listing->forceFill(['rank_score' => RankScore::for($listing)])->saveQuietly();
    }

    private function next(Listing $listing, string $step): RedirectResponse
    {
        return redirect()->route('account.listings.edit', [$listing, $step])->with('status', 'Saved.');
    }

    private function editorData(Request $request, Listing $listing, string $step): array
    {
        $listing->loadMissing(['translations', 'private', 'licence', 'island', 'area', 'owner']);
        $isAffiliate = $listing->exists ? $listing->isAffiliateListing() : $request->user()->isAffiliate();

        $steps = ['basics' => 'Basics', 'location' => 'Location', 'photo' => 'Photo', 'link' => 'Booking link'];
        if (! $isAffiliate) {
            $steps['licence'] = 'Licence';
        }
        $steps['review'] = 'Review and send';

        return [
            'listing' => $listing,
            'step' => $step,
            'steps' => $steps,
            'isAffiliate' => $isAffiliate,
            'islands' => Island::active()->with(['areas' => fn ($q) => $q->orderBy('sort_order')])->orderBy('sort_order')->get(),
            'missing' => $listing->exists ? ListingChecklist::missingForSubmit($listing) : [],
            'locked' => in_array($listing->status, [ListingStatus::PendingReview, ListingStatus::Archived], true),
        ];
    }
}
