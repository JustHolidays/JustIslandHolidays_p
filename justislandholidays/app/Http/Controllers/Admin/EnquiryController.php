<?php

namespace App\Http\Controllers\Admin;

use App\Models\Enquiry;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only enquiries, for handling complaints and spam. Owners reply from their own inbox.
 * Guest details are personal data: opening an enquiry is written to the audit log.
 */
class EnquiryController extends AdminController
{
    public function index(Request $request): View
    {
        $recentSince = now()->subDays((int) config('site.enquiries.recent_days'));

        $query = $this->ctx()->scopeVia(Enquiry::query(), 'listing')
            ->with(['listing.translations', 'listing.island']);

        if (in_array($status = $request->query('status'), ['new', 'replied', 'closed', 'spam'], true)) {
            $query->where('status', $status);
        }
        if (filled($q = $request->query('q')) && is_string($q)) {
            $like = $this->like($q);
            $query->where(fn ($w) => $w->where('guest_name', 'like', $like)->orWhere('guest_email', 'like', $like));
        }

        $query->latest('created_at')->latest('id');

        return view('admin.enquiries.index', [
            'enquiries' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'recentSince' => $recentSince,
        ]);
    }

    public function show(Request $request, Enquiry $enquiry): View
    {
        $enquiry->load(['listing.translations', 'listing.island', 'owner']);
        $this->ctx()->authorizeIsland($enquiry->listing?->island_id);

        Audit::record($request->user(), 'enquiry.view', $enquiry);

        return view('admin.enquiries.show', ['enquiry' => $enquiry]);
    }

    public function markSpam(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $enquiry->load('listing');
        $this->ctx()->authorizeIsland($enquiry->listing?->island_id);
        abort_unless($request->user()->can('markSpam', $enquiry), 403);

        $before = $enquiry->status;
        $enquiry->forceFill(['status' => 'spam'])->save();
        Audit::record($request->user(), 'enquiry.mark_spam', $enquiry, null, ['before' => ['status' => $before]]);

        return back()->with('status', 'Marked as spam.');
    }
}
