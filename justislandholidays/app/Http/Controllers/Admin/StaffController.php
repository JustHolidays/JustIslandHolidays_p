<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AccountStatus;
use App\Admin\Invitations;
use App\Enums\Role;
use App\Models\Island;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin accounts (super admins only). Island admins need at least one island.
 * Nobody changes their own role, and the last active super admin cannot be demoted or deactivated.
 */
class StaffController extends AdminController
{
    public function index(): View
    {
        return view('admin.staff.index', [
            'staff' => User::whereIn('role', [Role::IslandAdmin, Role::SuperAdmin])->with('islands')->orderBy('role')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.staff.form', ['staff' => new User(['name' => '']), 'islands' => Island::orderBy('sort_order')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $staff = DB::transaction(function () use ($data, $request) {
            $user = new User(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => Str::password(40)]);
            $user->role = Role::from($data['role']);
            $user->save();
            $this->syncIslands($user, $data, $request->user());
            Audit::record($request->user(), 'staff.create', $user, null, ['role' => $data['role'], 'islands' => $data['islands'] ?? []]);

            return $user;
        });

        Invitations::send($staff);

        return redirect()->route('admin.staff.index')->with('status', "{$staff->name} has been invited. They set a password and two-factor sign-in when they first sign in.");
    }

    public function edit(User $staff): View
    {
        $this->ensureStaff($staff);

        return view('admin.staff.form', ['staff' => $staff->load('islands'), 'islands' => Island::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $this->ensureStaff($staff);
        $data = $this->validated($request, $staff);

        if ($data['role'] !== $staff->role->value && Gate::denies('changeRole', $staff)) {
            throw ValidationException::withMessages(['role' => $request->user()->id === $staff->id
                ? 'You cannot change your own role.'
                : 'The last active super admin must stay a super admin.']);
        }

        DB::transaction(function () use ($staff, $data, $request) {
            $before = ['name' => $staff->name, 'email' => $staff->email, 'role' => $staff->role->value, 'islands' => $staff->islands->pluck('id')->all()];
            $staff->forceFill(['name' => $data['name'], 'email' => strtolower($data['email']), 'role' => Role::from($data['role'])])->save();
            // A change of islands applies on the admin's next request (islands are read per request).
            $this->syncIslands($staff, $data, $request->user());

            Audit::record($request->user(), 'staff.update', $staff, null, [
                'before' => $before,
                'after' => ['name' => $staff->name, 'email' => $staff->email, 'role' => $staff->role->value, 'islands' => $staff->islands()->pluck('islands.id')->all()],
            ]);
        });

        return redirect()->route('admin.staff.index')->with('status', 'Saved.');
    }

    public function deactivate(Request $request, User $staff, AccountStatus $accounts): RedirectResponse
    {
        $this->ensureStaff($staff);
        $accounts->deactivate($staff, $request->user(), $this->reason($request));

        return back()->with('status', "{$staff->name} has been deactivated and signed out everywhere.");
    }

    public function reactivate(Request $request, User $staff, AccountStatus $accounts): RedirectResponse
    {
        $this->ensureStaff($staff);
        $accounts->reactivate($staff, $request->user());

        return back()->with('status', "{$staff->name} is active again.");
    }

    /** Removes their authenticator app (for a lost phone); they get emailed sign-in codes instead. */
    public function resetTwoFactor(Request $request, User $staff, AccountStatus $accounts): RedirectResponse
    {
        $this->ensureStaff($staff);
        $staff->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $accounts->endSessions($staff);
        Audit::record($request->user(), 'staff.reset_two_factor', $staff);

        return back()->with('status', "Authenticator app removed for {$staff->name}. They now get a sign-in code by email.");
    }

    public function signOut(Request $request, User $staff, AccountStatus $accounts): RedirectResponse
    {
        $this->ensureStaff($staff);
        $accounts->endSessions($staff);
        Audit::record($request->user(), 'staff.sign_out', $staff);

        return back()->with('status', "{$staff->name} has been signed out everywhere.");
    }

    public function invite(Request $request, User $staff): RedirectResponse
    {
        $this->ensureStaff($staff);
        Invitations::send($staff);
        Audit::record($request->user(), 'staff.invite', $staff);

        return back()->with('status', 'A new invitation has been emailed.');
    }

    private function validated(Request $request, ?User $staff = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($staff?->id)],
            'role' => ['required', Rule::in(['island_admin', 'super_admin'])],
            'islands' => ['array', Rule::requiredIf($request->input('role') === 'island_admin')],
            'islands.*' => ['integer', 'exists:islands,id'],
        ], ['islands.required' => 'An island admin needs at least one island.']);
    }

    private function syncIslands(User $user, array $data, User $by): void
    {
        $ids = $data['role'] === 'island_admin' ? array_map('intval', $data['islands'] ?? []) : [];
        $user->islands()->sync(collect($ids)->mapWithKeys(fn ($id) => [$id => ['assigned_by' => $by->id, 'created_at' => now()]])->all());
        $user->forgetManagedIslands();
        $user->unsetRelation('islands');
    }

    private function ensureStaff(User $user): void
    {
        abort_unless($user->isAdmin(), 404);
    }
}
