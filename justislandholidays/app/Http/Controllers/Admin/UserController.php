<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AccountStatus;
use App\Billing\AccountPlan;
use App\Enums\Role;
use App\Models\AdminRecommendation;
use App\Models\Agency;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Owners and agents. Super admins edit, change role and deactivate. Island admins see,
 * read-only, the owners and agents with listings on their islands, and can recommend a suspension.
 */
class UserController extends AdminController
{
    public function index(Request $request, AccountPlan $plans): View
    {
        $ctx = $this->ctx();

        $query = User::query()
            ->whereIn('role', [Role::Owner, Role::Agent])
            ->with('agency')
            ->withCount('listings');

        // Island admins (or a selected island): only people with listings on those islands.
        if (! $ctx->admin()->isSuperAdmin() || $ctx->selectedIslandId()) {
            $query->whereHas('listings', fn (Builder $q) => $ctx->scope($q));
        }

        if (in_array($role = $request->query('role'), ['owner', 'agent'], true)) {
            $query->where('role', $role);
        }
        if ($request->query('state') === 'deactivated') {
            $query->whereNotNull('deactivated_at');
        }
        if (filled($q = $request->query('q')) && is_string($q)) {
            $like = $this->like($q);
            $query->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
        }

        $this->sort($query, $request, ['name' => 'name', 'login' => 'last_login_at', 'joined' => 'created_at', 'listings' => 'listings_count'], 'joined');

        $users = $query->paginate(self::PER_PAGE)->withQueryString();
        $planNames = $users->getCollection()->mapWithKeys(fn (User $u) => [$u->id => $plans->planFor($plans->accountFor($u))->name]);

        return view('admin.users.index', ['users' => $users, 'planNames' => $planNames]);
    }

    public function show(User $user, AccountPlan $plans): View
    {
        abort_unless(in_array($user->role, [Role::Owner, Role::Agent], true), 404);

        $listings = $this->ctx()->scope($user->listings()->getQuery())->with(['island', 'translations'])->get();

        return view('admin.users.show', [
            'user' => $user->load('agency'),
            'listings' => $listings,
            'plan' => $plans->planFor($plans->accountFor($user)),
            'agencies' => Agency::orderBy('name')->get(),
            'openRecommendation' => AdminRecommendation::where('user_id', $user->id)->where('status', 'open')->first(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless(in_array($user->role, [Role::Owner, Role::Agent], true), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(['owner', 'agent'])],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
        ]);

        if ($data['role'] !== $user->role->value && Gate::denies('changeRole', $user)) {
            throw ValidationException::withMessages(['role' => 'You cannot change this role.']);
        }

        $before = $user->only(['name', 'email', 'phone', 'role', 'agency_id']);
        $user->forceFill([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'role' => Role::from($data['role']),
            'agency_id' => $data['role'] === 'agent' ? ($data['agency_id'] ?? null) : null,
        ])->save();

        Audit::record($request->user(), 'user.update', $user, null, ['before' => $before, 'after' => $user->only(['name', 'email', 'phone', 'role', 'agency_id'])]);

        return back()->with('status', 'Saved.');
    }

    public function deactivate(Request $request, User $user, AccountStatus $accounts): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $accounts->deactivate($user, $request->user(), $this->reason($request));

        return back()->with('status', 'Account deactivated. Its listings are paused and the reason has been emailed.');
    }

    public function reactivate(Request $request, User $user, AccountStatus $accounts): RedirectResponse
    {
        $accounts->reactivate($user, $request->user());

        return back()->with('status', 'Account active again. Its listings stay paused until the owner makes them live.');
    }

    /** Island admins cannot suspend an owner; they recommend it to a super admin. */
    public function recommendSuspension(Request $request, User $user): RedirectResponse
    {
        abort_unless(Gate::allows('recommendSuspension', $user), 403);
        $reason = $this->reason($request);

        if (AdminRecommendation::where('user_id', $user->id)->where('status', 'open')->exists()) {
            return back()->withErrors(['reason' => 'A recommendation for this account is already waiting for a super admin.']);
        }

        $islandId = $user->listings()->whereIn('island_id', $request->user()->managedIslandIds())->value('island_id');
        $rec = new AdminRecommendation(['action' => 'suspend_user', 'user_id' => $user->id, 'island_id' => $islandId, 'reason' => $reason]);
        $rec->recommended_by = $request->user()->id;
        $rec->save();
        Audit::record($request->user(), 'recommendation.create', $rec, null, ['user_id' => $user->id, 'reason' => $reason]);

        return back()->with('status', 'Recommendation sent. A super admin will decide.');
    }
}
