<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Invitations;
use App\Enums\Role;
use App\Models\Agency;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Add an owner or agent account (super admins only, 20 Nov 2026). The account starts with a
 * random password nobody knows; the person gets an invitation to set their own (valid 7 days),
 * which also confirms their email address. Properties are then added for them with
 * "Add a property", or by them once they have signed in.
 */
class OwnerCreateController extends AdminController
{
    public function create(Request $request): View
    {
        return view('admin.users.create', [
            'agencies' => Agency::orderBy('name')->get(['id', 'name']),
            'role' => $request->query('as') === 'agent' ? 'agent' : 'owner',
        ]);
    }

    /** Sends (again) the email to set a password, for an owner or agent who has not signed in yet. */
    public function invite(Request $request, User $user): RedirectResponse
    {
        abort_unless(in_array($user->role, [Role::Owner, Role::Agent], true) && ! $user->isDeactivated(), 404);
        Invitations::send($user);
        Audit::record($request->user(), 'user.invite', $user);

        return back()->with('status', "Invitation emailed to {$user->email}. It works for 7 days.");
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in([Role::Owner->value, Role::Agent->value])],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'invite' => ['nullable', 'boolean'],
        ], [
            'email.unique' => 'There is already an account with this email address.',
            'role.in' => 'Choose owner or agent.',
        ]);

        $user = DB::transaction(function () use ($data, $request) {
            $user = new User(['name' => trim($data['name']), 'email' => $data['email'], 'phone' => $data['phone'] ?? null, 'password' => Str::password(40)]);
            $user->role = Role::from($data['role']);
            $user->agency_id = $data['role'] === Role::Agent->value ? ($data['agency_id'] ?? null) : null;
            $user->save();
            Audit::record($request->user(), 'user.admin_create', $user, null, ['role' => $data['role'], 'invited' => $request->boolean('invite')]);

            return $user;
        });

        if ($request->boolean('invite')) {
            Invitations::send($user);
        }

        $label = $user->role === Role::Agent ? 'Agent' : 'Owner';

        return redirect()->route($user->role === Role::Agent ? 'admin.agents.show' : 'admin.users.show', $user)
            ->with('status', "{$label} account created for {$user->name}. "
                .($request->boolean('invite') ? 'An invitation to set a password has been emailed (it works for 7 days).' : 'No invitation was sent: use Send invitation on this page when they are ready.'));
    }
}
