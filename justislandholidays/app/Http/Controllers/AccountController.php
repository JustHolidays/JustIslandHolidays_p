<?php

namespace App\Http\Controllers;

use App\Billing\AccountPlan;
use App\Models\Enquiry;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function home(Request $request, AccountPlan $plans): View
    {
        $user = $request->user();
        $isLister = $user->role?->isLister() ?? false;

        $listingCounts = $isLister
            ? Listing::where('owner_id', $user->id)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)
            : collect();

        return view('account.home', [
            'user' => $user,
            'isLister' => $isLister,
            'listingCounts' => $listingCounts,
            'newEnquiries' => $isLister ? Enquiry::where('owner_id', $user->id)->where('status', 'new')->count() : 0,
            'enquiries30' => $isLister ? Enquiry::where('owner_id', $user->id)->where('created_at', '>=', now()->subDays(30))->count() : 0,
            'plan' => $user->can('use-billing') ? $plans->planFor($plans->accountFor($user)) : null,
        ]);
    }

    /** Two-factor set-up. Required before an admin can open the admin area. */
    public function security(Request $request): View
    {
        return view('account.security', ['user' => $request->user()]);
    }
}
