<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The lister's enquiry inbox. Replies happen by email; here owners track status. */
class AccountEnquiryController extends Controller
{
    private const STATUSES = ['new' => 'New', 'replied' => 'Replied', 'closed' => 'Closed', 'spam' => 'Spam'];

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $status = array_key_exists($status, self::STATUSES) ? $status : null;

        $enquiries = Enquiry::where('owner_id', $request->user()->id)
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'spam'))
            ->with('listing.translations')
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('account.enquiries.index', [
            'enquiries' => $enquiries,
            'status' => $status,
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(Enquiry $enquiry): View
    {
        $enquiry->load('listing.translations');

        return view('account.enquiries.show', ['enquiry' => $enquiry, 'statuses' => self::STATUSES]);
    }

    public function updateStatus(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(self::STATUSES))]]);

        $enquiry->forceFill(['status' => $data['status']])->save();

        return back()->with('status', 'Enquiry marked as '.strtolower(self::STATUSES[$data['status']]).'.');
    }
}
