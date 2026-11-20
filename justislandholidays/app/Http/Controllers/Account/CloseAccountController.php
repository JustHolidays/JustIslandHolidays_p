<?php

namespace App\Http\Controllers\Account;

use App\Admin\AccountClosure;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** "Close my account" (20 Nov 2026): a request the super admins action; it can be withdrawn while it waits. */
class CloseAccountController extends Controller
{
    public function store(Request $request, AccountClosure $closures): RedirectResponse
    {
        abort_unless(AccountClosure::canRequest($request->user()), 403);
        if ($request->user()->closure_requested_at !== null) {
            return back()->with('status', 'Your request is already with us.');
        }
        $data = $request->validate(['closure_reason' => ['nullable', 'string', 'max:500']]);

        $closures->request($request->user(), trim((string) ($data['closure_reason'] ?? '')) ?: null);

        return redirect()->to(route('account.home').'#close-account')
            ->with('status', 'Your request to close your account has been sent. We will close it and email you, usually within a few days.');
    }

    public function destroy(Request $request, AccountClosure $closures): RedirectResponse
    {
        abort_unless(AccountClosure::canRequest($request->user()), 403);
        $closures->withdraw($request->user());

        return redirect()->to(route('account.home').'#close-account')->with('status', 'Your request is withdrawn. Your account stays open.');
    }
}
