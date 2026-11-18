<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnquiryRequest;
use App\Jobs\SendEnquiryEmail;
use App\Models\Enquiry;
use App\Models\Listing;
use App\Support\IpHasher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class EnquiryController extends Controller
{
    /**
     * Stores the enquiry and emails it to the owner (reply-to the visitor).
     * Spam control: honeypot, ALTCHA (in the form request) and a per-IP-hash limit.
     */
    public function store(StoreEnquiryRequest $request, string $lang): RedirectResponse
    {
        $listing = Listing::live()
            ->where('public_ref', $request->input('listing_ref'))
            ->with(['island', 'area', 'owner'])
            ->firstOrFail();

        abort_if($listing->isAffiliateListing(), 404);   // affiliate listings take bookings on their own site

        $back = $listing->publicUrl($lang).'#enquiry';

        // Bots that fill the hidden field see a normal success message; nothing is stored.
        if ($request->isHoneypotFilled()) {
            return redirect()->to($back)->with('enquiry_sent', true);
        }

        $ipHash = IpHasher::hash($request->ip());
        $key = 'enquiry:'.$ipHash;

        if (RateLimiter::tooManyAttempts($key, (int) config('site.enquiries.per_ip_per_hour'))) {
            throw ValidationException::withMessages([
                'message' => __('You have sent several enquiries in a short time. Please try again in an hour.'),
            ])->redirectTo($back);
        }

        $enquiry = new Enquiry($request->safe()->only([
            'guest_name', 'guest_email', 'guest_phone', 'check_in', 'check_out', 'adults', 'children', 'message',
        ]));
        $enquiry->listing_id = $listing->id;
        $enquiry->owner_id = $listing->owner_id;
        $enquiry->lang = $lang;
        $enquiry->consent_given_at = now();
        $enquiry->ip_hash = $ipHash;
        $enquiry->save();

        RateLimiter::hit($key, 3600);

        SendEnquiryEmail::dispatch($enquiry->id)->afterCommit();

        return redirect()->to($back)->with('enquiry_sent', true);
    }
}
