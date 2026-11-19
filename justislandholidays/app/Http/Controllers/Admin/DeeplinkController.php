<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Listings\DeeplinkChecker;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use App\Listings\ListingWorkflow;
use App\Listings\ListingWorkflowException;
use App\Models\Listing;
use App\Models\SiteSetting;
use App\Notifications\AccountNotice;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Deeplink monitor, most-used broken links first, in two views:
 * - each affiliate's links, from the Affiliates menu (super admins);
 * - owner and agent links, for every admin within their islands.
 */
class DeeplinkController extends AdminController
{
    /** Owner and agent links (everything not added for an affiliate). */
    public function index(Request $request): View
    {
        $query = $this->ctx()->scope(Listing::query())
            ->whereHas('owner', fn (Builder $q) => $q->where('role', '!=', Role::Affiliate->value));

        return $this->monitor($request, $query, null);
    }

    /** One affiliate's links (super admins, through can:manage-affiliates). */
    public function affiliate(Request $request, User $affiliate): View
    {
        abort_unless($affiliate->isAffiliate(), 404);

        return $this->monitor($request, Listing::where('owner_id', $affiliate->id), $affiliate);
    }

    private function monitor(Request $request, Builder $query, ?User $affiliate): View
    {
        $since = now()->subDays(7);

        $query->whereNotNull('deeplink_url')
            ->with(['island', 'owner', 'translations'])
            ->withCount(['clicks as clicks_7' => fn ($q) => $q->where('is_bot', false)->where('clicked_at', '>=', $since)]);

        if (in_array($status = $request->query('status'), ['unchecked', 'ok', 'broken'], true)) {
            $query->where('deeplink_status', $status);
        }

        $query->orderByRaw("CASE deeplink_status WHEN 'broken' THEN 0 WHEN 'unchecked' THEN 1 ELSE 2 END")
            ->orderByDesc('clicks_7');

        return view('admin.deeplinks.index', [
            'listings' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'allowedHosts' => DeeplinkChecker::allowedHosts(),
            'affiliate' => $affiliate,
        ]);
    }

    public function recheck(Request $request, Listing $listing, DeeplinkChecker $checker): RedirectResponse
    {
        $this->ctx()->authorizeIsland($listing->island_id);
        $result = $checker->check($listing, $request->user());

        return back()->with('status', ($result['status'] === 'ok' ? 'Working. ' : 'Broken. ').$result['detail']);
    }

    /** Switching off marks the link broken: the banner disappears and /go/{ref} shows the listing page. */
    public function switchOff(Request $request, Listing $listing): RedirectResponse
    {
        $this->ctx()->authorizeIsland($listing->island_id);
        $listing->forceFill(['deeplink_status' => 'broken', 'deeplink_checked_at' => now()])->saveQuietly();
        Audit::record($request->user(), 'deeplink.switch_off', $listing);

        return back()->with('status', 'Link switched off. Visitors no longer see the Check availability button.');
    }

    public function pause(Request $request, Listing $listing, ListingWorkflow $workflow): RedirectResponse
    {
        $this->ctx()->authorizeIsland($listing->island_id);

        try {
            $workflow->pause($listing, $request->user(), $this->reason($request));
        } catch (ListingWorkflowException $e) {
            return back()->withErrors([$e->getMessage()]);
        }

        return back()->with('status', 'Listing paused. The lister has been emailed.');
    }

    public function message(Request $request, Listing $listing): RedirectResponse
    {
        $this->ctx()->authorizeIsland($listing->island_id);
        $text = $request->validate(['message' => ['required', 'string', 'min:3', 'max:1000']])['message'];

        $listing->owner->notify(new AccountNotice('deeplink.message', $text, $listing->title('en')));
        Audit::record($request->user(), 'deeplink.message', $listing, null, ['message' => $text]);

        return back()->with('status', 'Message sent to the lister.');
    }

    /** Which booking sites links may point to. Empty means any https address. Super admins only. */
    public function allowedHosts(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $raw = (string) $request->validate(['hosts' => ['nullable', 'string', 'max:2000']])['hosts'];

        $hosts = collect(preg_split('/[\s,]+/', strtolower($raw)) ?: [])
            ->map(fn ($h) => trim(preg_replace('#^https?://#', '', $h), '/.'))
            ->filter(fn ($h) => preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $h))
            ->unique()->values()->all();

        $before = DeeplinkChecker::allowedHosts();
        SiteSetting::updateOrCreate(['setting_key' => 'deeplinks.allowed_hosts'], ['value' => ['hosts' => $hosts], 'updated_by' => $request->user()->id]);
        Audit::record($request->user(), 'setting.update', 'site_setting', 'deeplinks.allowed_hosts', ['before' => $before, 'after' => $hosts]);

        return back()->with('status', $hosts === [] ? 'Any https booking site is allowed.' : 'Allowed booking sites saved.');
    }
}
