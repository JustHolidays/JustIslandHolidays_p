<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AccountClosure;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Settings › Account closures (super admins only, 20 Nov 2026): requests waiting, oldest first. */
class AccountClosureController extends AdminController
{
    public function index(): View
    {
        $requests = User::whereNotNull('closure_requested_at')
            ->whereIn('role', collect(AccountClosure::ROLES)->map->value)
            ->orderBy('closure_requested_at')
            ->get();

        return view('admin.closures', [
            'requests' => $requests,
            'listings' => Listing::whereIn('owner_id', $requests->pluck('id'))->where('status', '!=', 'archived')
                ->selectRaw('owner_id, COUNT(*) as n')->groupBy('owner_id')->pluck('n', 'owner_id'),
            'plans' => app(\App\Billing\AccountPlan::class),
        ]);
    }

    public function close(Request $request, User $user, AccountClosure $closures): RedirectResponse
    {
        $name = $user->name;
        $closures->close($user, $request->user());

        return back()->with('status', "{$name}'s account is closed. They have been emailed, and their personal details are removed in 30 days.");
    }

    public function decline(Request $request, User $user, AccountClosure $closures): RedirectResponse
    {
        abort_if($user->closure_requested_at === null, 404);
        $data = $request->validateWithBag('decline'.$user->id, ['reason' => ['required', 'string', 'min:3', 'max:500']], [
            'reason.required' => 'Give a reason. It is emailed to them.',
        ]);

        $closures->decline($user, $request->user(), trim($data['reason']));

        return back()->with('status', "{$user->name} has been told why their account is not closed.");
    }
}
