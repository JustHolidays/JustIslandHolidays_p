<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ListingStatus;
use App\Listings\DeeplinkChecker;
use App\Listings\ListingChecklist;
use App\Listings\ListingWorkflow;
use App\Listings\ListingWorkflowException;
use App\Listings\RankScore;
use App\Models\AdminRecommendation;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\ListingTranslation;
use App\Models\PropertyType;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ListingController extends AdminController
{
    public function __construct(private ListingWorkflow $workflow)
    {
    }

    public function index(Request $request): View
    {
        $ctx = $this->ctx();
        $tab = $request->query('tab') === 'recommended' && $ctx->admin()->isSuperAdmin() ? 'recommended' : 'all';
        $status = $request->query('status', $tab === 'recommended' ? 'all' : 'pending_review');

        $query = $ctx->scope(Listing::query())
            ->withSubmittedAt()
            ->with(['island', 'area', 'owner', 'translations', 'licence']);

        if ($tab === 'recommended') {
            $query->whereIn('id', AdminRecommendation::where('action', 'reject_listing')->where('status', 'open')->select('listing_id'));
        }
        if ($status !== 'all' && in_array($status, array_column(ListingStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }
        if ($area = $request->integer('area')) {
            $query->where('area_id', $area);
        }
        if (in_array($role = $request->query('role'), ['owner', 'agent', 'affiliate'], true)) {
            $query->whereHas('owner', fn (Builder $q) => $q->where('role', $role));
        }
        if (in_array($type = $request->query('type'), PropertyType::cached()->keys()->all(), true)) {
            $query->where('property_type', $type);
        }
        if (in_array($licence = $request->query('licence'), ['pending', 'verified', 'rejected', 'not_provided'], true)) {
            $query->whereHas('licence', fn (Builder $q) => $q->where('status', $licence));
        }
        if (in_array($deeplink = $request->query('deeplink'), ['unchecked', 'ok', 'broken'], true)) {
            // Check results exist for affiliate links only.
            $query->whereNotNull('deeplink_url')->where('deeplink_status', $deeplink)->whereHas('owner', fn (Builder $q) => $q->where('role', 'affiliate'));
        } elseif ($deeplink === 'lister') {
            $query->whereNotNull('deeplink_url')->whereHas('owner', fn (Builder $q) => $q->where('role', '!=', 'affiliate'));
        } elseif ($deeplink === 'none') {
            $query->whereNull('deeplink_url');
        }
        if (filled($q = $request->query('q')) && is_string($q)) {
            $like = $this->like($q);
            $query->where(fn (Builder $w) => $w
                ->whereHas('translations', fn (Builder $t) => $t->where('title', 'like', $like))
                ->orWhereHas('owner', fn (Builder $o) => $o->where('email', 'like', $like)));
        }

        // Pending review: oldest first, so the longest-waiting listing is at the top.
        $this->sort($query, $request, [
            'submitted' => fn ($q, $dir) => $q->orderBy('submitted_at', $dir),
            'updated' => 'listings.updated_at',
            'type' => 'property_type',
        ], 'submitted', $status === 'pending_review' ? 'asc' : 'desc');

        return view('admin.listings.index', [
            'listings' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'status' => $status,
            'tab' => $tab,
            'areas' => $ctx->scope(Area::query())->orderBy('island_id')->orderBy('sort_order')->get(),
            'target' => now()->subHours((int) config('site.review_target_hours')),
        ]);
    }

    public function show(Listing $listing): View
    {
        $this->ctx()->authorizeIsland($listing->island_id);
        $listing->load(['island', 'area', 'owner', 'translations', 'licence.verifier', 'amenities', 'private']);

        return view('admin.listings.show', [
            'listing' => $listing,
            'missing' => ListingChecklist::missingForApproval($listing),
            'history' => AuditLog::with('actor')->where('entity', 'listing')->where('entity_id', (string) $listing->id)->latest('id')->get(),
            'recommendations' => AdminRecommendation::with(['recommender', 'decider'])->where('listing_id', $listing->id)->latest('id')->get(),
            'areas' => Area::where('island_id', $listing->island_id)->orderBy('sort_order')->get(),
            'hostAllowed' => DeeplinkChecker::isAllowedHost($listing->deeplink_url),
            // Page views (20 Nov 2026): last 30 days, last 365 days and all time.
            'views' => [
                'Last 30 days' => \App\Listings\PageViews::totals([$listing->id], now()->subDays(30)->toDateString())[$listing->id] ?? 0,
                'Last 365 days' => \App\Listings\PageViews::totals([$listing->id], now()->subDays(365)->toDateString())[$listing->id] ?? 0,
                'All time' => \App\Listings\PageViews::totals([$listing->id])[$listing->id] ?? 0,
            ],
        ]);
    }

    public function approve(Request $request, Listing $listing): RedirectResponse
    {
        return $this->run(fn () => $this->workflow->approve($listing, $request->user()), $listing, 'Approved. The listing is live and the lister has been emailed.');
    }

    /** Affiliate drafts: send for review on the affiliate's behalf and approve, in one step. */
    public function publishDraft(Request $request, Listing $listing): RedirectResponse
    {
        return $this->run(fn () => $this->workflow->publishAffiliateDraft($listing, $request->user()), $listing, 'Published. The listing is live.');
    }

    public function reject(Request $request, Listing $listing): RedirectResponse
    {
        $reason = $this->reason($request);

        return $this->run(fn () => $this->workflow->reject($listing, $request->user(), $reason), $listing, 'Rejected. The reason has been emailed to the lister.');
    }

    /** Island admins cannot reject; they recommend it, and a super admin decides. */
    public function recommendRejection(Request $request, Listing $listing): RedirectResponse
    {
        $this->ctx()->authorizeIsland($listing->island_id);
        abort_unless(Gate::allows('recommendRejection', $listing), 403);
        $reason = $this->reason($request);

        if (AdminRecommendation::where('listing_id', $listing->id)->where('status', 'open')->exists()) {
            return back()->withErrors(['reason' => 'A recommendation for this listing is already waiting for a super admin.']);
        }

        $rec = new AdminRecommendation(['action' => 'reject_listing', 'listing_id' => $listing->id, 'island_id' => $listing->island_id, 'reason' => $reason]);
        $rec->recommended_by = $request->user()->id;
        $rec->save();
        Audit::record($request->user(), 'recommendation.create', $rec, null, ['listing_id' => $listing->id, 'reason' => $reason]);

        return back()->with('status', 'Recommendation sent. A super admin will decide.');
    }

    public function pause(Request $request, Listing $listing): RedirectResponse
    {
        $reason = $this->reason($request);

        return $this->run(fn () => $this->workflow->pause($listing, $request->user(), $reason), $listing, 'Paused. The reason has been emailed to the lister.');
    }

    public function archive(Request $request, Listing $listing): RedirectResponse
    {
        $reason = $this->reason($request);

        return $this->run(fn () => $this->workflow->archive($listing, $request->user(), $reason), $listing, 'Archived. The reason has been emailed to the lister.');
    }

    /** Pause or archive many listings at once. Approving and rejecting are one at a time. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['pause', 'archive'])],
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], ['ids.required' => 'Tick at least one listing.']);

        $listings = $this->ctx()->scope(Listing::query())->whereIn('id', $data['ids'])->get();
        $done = 0;
        $problems = [];

        foreach ($listings as $listing) {
            try {
                $data['action'] === 'pause'
                    ? $this->workflow->pause($listing, $request->user(), $data['reason'])
                    : $this->workflow->archive($listing, $request->user(), $data['reason']);
                $done++;
            } catch (ListingWorkflowException $e) {
                $problems[] = $listing->title('en').': '.$e->getMessage();
            }
        }

        $redirect = back()->with('status', "{$done} listing(s) ".($data['action'] === 'pause' ? 'paused' : 'archived').'.');

        return $problems ? $redirect->withErrors($problems) : $redirect;
    }

    /** Admin corrections to the details. Every edit is audited with before and after. */
    public function update(Request $request, Listing $listing): RedirectResponse
    {
        $this->ctx()->authorizeIsland($listing->island_id);
        abort_unless(Gate::allows('update', $listing), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:500'],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('island_id', $listing->island_id)],
            'property_type' => ['required', Rule::in(PropertyType::allowedFor($listing->property_type))],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'sleeps' => ['required', 'integer', 'min:1', 'max:40'],
            'has_private_pool' => ['nullable', 'boolean'],
            'pets' => ['required', Rule::in(['yes', 'no', 'not_stated'])],
            'image_alt' => ['nullable', 'string', 'max:160'],
            // Owners and agents must have a From price (19 Nov 2026); affiliates may leave it out.
            'price' => [$listing->isAffiliateListing() ? 'nullable' : 'required', 'integer', 'min:1', 'max:1000000'],
        ], [
            'price.required' => 'Add the From price per week. An owner or agent property cannot go live without one.',
            'price.min' => 'The From price must be more than 0.',
        ]);

        DB::transaction(function () use ($listing, $data, $request) {
            $en = $listing->translations()->where('lang', 'en')->first();
            $fields = ['area_id', 'property_type', 'bedrooms', 'bathrooms', 'sleeps', 'has_private_pool', 'pets_allowed', 'image_alt', 'min_price_week_cents'];
            $before = $listing->only($fields) + ['title' => $en?->title, 'summary' => $en?->summary];

            $listing->fill([
                'area_id' => $data['area_id'],
                'property_type' => $data['property_type'],
                'bedrooms' => $data['bedrooms'],
                'bathrooms' => $data['bathrooms'],
                'sleeps' => $data['sleeps'],
                'has_private_pool' => (bool) ($data['has_private_pool'] ?? false),
                'pets_allowed' => ['yes' => true, 'no' => false, 'not_stated' => null][$data['pets']],
                'image_alt' => $data['image_alt'] ?? $listing->image_alt,
                'min_price_week_cents' => isset($data['price']) ? $data['price'] * 100 : null,
            ])->save();
            ListingTranslation::query()->updateOrInsert(['listing_id' => $listing->id, 'lang' => 'en'], ['title' => $data['title'], 'summary' => $data['summary']]);
            $listing->forceFill(['rank_score' => RankScore::for($listing->fresh('translations'))])->saveQuietly();

            Audit::record($request->user(), 'listing.admin_edit', $listing, null, [
                'before' => $before,
                'after' => $listing->fresh()->only($fields) + ['title' => $data['title'], 'summary' => $data['summary']],
            ]);
        });

        return back()->with('status', 'Changes saved.');
    }

    /** Opens the destination for checking. Records no click. */
    public function testLink(Listing $listing): RedirectResponse
    {
        $this->ctx()->authorizeIsland($listing->island_id);
        abort_unless($listing->deeplink_url, 404);

        return redirect()->away($listing->deeplink_url)->withHeaders(['Referrer-Policy' => 'no-referrer']);
    }

    /** Recheck now, or mark the link as checked by hand after testing it. */
    public function checkLink(Request $request, Listing $listing, DeeplinkChecker $checker): RedirectResponse
    {
        $this->ctx()->authorizeIsland($listing->island_id);
        abort_unless($listing->deeplink_url, 404);
        // Owners and agents look after their own links: there is nothing for us to check.
        abort_unless($listing->isAffiliateListing(), 404);

        if ($request->input('mode') === 'manual_ok') {
            $listing->forceFill(['deeplink_status' => 'ok', 'deeplink_checked_at' => now()])->saveQuietly();
            Audit::record($request->user(), 'deeplink.manual_ok', $listing);

            return back()->with('status', 'Marked as checked: the link opens the right property.');
        }

        $result = $checker->check($listing, $request->user());

        return back()->with('status', $result['status'] === 'ok' ? 'The link works. '.$result['detail'] : 'The link looks broken. '.$result['detail']);
    }

    private function run(callable $action, Listing $listing, string $success): RedirectResponse
    {
        $this->ctx()->authorizeIsland($listing->island_id);

        try {
            $action();
        } catch (ListingWorkflowException $e) {
            return back()->withErrors(array_merge([$e->getMessage()], $e->problems));
        }

        return redirect()->route('admin.listings.show', $listing)->with('status', $success);
    }
}
