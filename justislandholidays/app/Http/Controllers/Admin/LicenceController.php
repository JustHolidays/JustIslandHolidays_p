<?php

namespace App\Http\Controllers\Admin;

use App\Models\Listing;
use App\Models\ListingLicence;
use App\Models\SiteSetting;
use App\Notifications\AccountNotice;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Licence checks are manual: the admin looks the number up in the official register
 * of the Balearic Islands and records the result here. Nothing is checked automatically.
 */
class LicenceController extends AdminController
{
    public function index(Request $request): View
    {
        $query = $this->ctx()->scopeVia(ListingLicence::query(), 'listing')
            ->where('status', 'pending')
            ->whereHas('listing.owner', fn ($q) => $q->where('role', '!=', 'affiliate'))
            ->with(['listing.island', 'listing.owner', 'listing.translations'])
            ->select('listing_licences.*')
            ->selectRaw(str_replace('listings.id', 'listing_licences.listing_id', Listing::submittedAtSql()).' AS submitted_at')
            ->orderBy('submitted_at');

        return view('admin.licences.index', [
            'licences' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'registerUrl' => SiteSetting::find('licences.register_url')?->value['url'] ?? null,
        ]);
    }

    public function verify(Request $request, Listing $listing): RedirectResponse
    {
        $licence = $this->licence($listing, 'verify');
        $note = $request->validate(['notes' => ['nullable', 'string', 'max:500']])['notes'] ?? null;

        $licence->forceFill(['status' => 'verified', 'verified_by' => $request->user()->id, 'verified_at' => now(), 'notes' => $note])->save();
        Audit::record($request->user(), 'licence.verify', 'listing_licence', $listing->id, ['registration_number' => $licence->registration_number, 'national_registry_no' => $licence->national_registry_no]);

        return back()->with('status', 'Licence verified. The listing can now be approved.');
    }

    public function reject(Request $request, Listing $listing): RedirectResponse
    {
        $licence = $this->licence($listing, 'reject');
        $note = $this->reason($request, 'notes');

        $licence->forceFill(['status' => 'rejected', 'verified_by' => $request->user()->id, 'verified_at' => now(), 'notes' => $note])->save();
        Audit::record($request->user(), 'licence.reject', 'listing_licence', $listing->id, ['reason' => $note]);

        $listing->owner->notify(new AccountNotice('licence.rejected', $note, $listing->title('en')));

        return back()->with('status', 'Licence not accepted. The lister has been emailed and the listing cannot go live.');
    }

    public function document(Listing $listing): StreamedResponse
    {
        $licence = $this->licence($listing, 'viewDocument');
        abort_unless($licence->document_path && Storage::disk('local')->exists($licence->document_path), 404);

        Audit::record(request()->user(), 'licence.document_view', 'listing_licence', $listing->id);

        return Storage::disk('local')->download($licence->document_path);
    }

    /** Super admins set the address of the official register (it is not hard-coded). */
    public function registerUrl(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $url = $request->validate(['url' => ['nullable', 'url:https', 'max:500']])['url'] ?? null;

        SiteSetting::updateOrCreate(['setting_key' => 'licences.register_url'], ['value' => ['url' => $url], 'updated_by' => $request->user()->id]);
        Audit::record($request->user(), 'setting.update', 'site_setting', 'licences.register_url', ['after' => $url]);

        return back()->with('status', 'Register address saved.');
    }

    private function licence(Listing $listing, string $ability): ListingLicence
    {
        $this->ctx()->authorizeIsland($listing->island_id);
        $licence = $listing->licence;
        abort_if($licence === null, 404);
        abort_unless(Gate::allows($ability, $licence), 403);

        return $licence;
    }
}
