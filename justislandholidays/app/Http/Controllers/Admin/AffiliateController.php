<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AccountStatus;
use App\Admin\Invitations;
use App\Enums\ListingStatus;
use App\Enums\Role;
use App\Models\AffiliateCommission;
use App\Models\DeeplinkClick;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Affiliate accounts (super admins only). Affiliates cannot sign themselves up. */
class AffiliateController extends AdminController
{
    public function index(Request $request): View
    {
        $since = now()->subDays(30);

        $query = User::where('role', Role::Affiliate)
            ->withCount([
                'listings',
                'listings as live_count' => fn ($q) => $q->where('status', ListingStatus::Live),
                'listings as broken_links' => fn ($q) => $q->whereNotNull('deeplink_url')->where('deeplink_status', 'broken'),
            ])
            ->addSelect(['clicks_30' => DeeplinkClick::selectRaw('COUNT(*)')
                ->whereColumn('affiliate_id', 'users.id')->where('is_bot', false)->where('clicked_at', '>=', $since)]);

        if (filled($q = $request->query('q')) && is_string($q)) {
            $like = $this->like($q);
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
        }
        $this->sort($query, $request, ['name' => 'name', 'clicks' => 'clicks_30', 'listings' => 'listings_count'], 'name', 'asc');

        $affiliates = $query->paginate(self::PER_PAGE)->withQueryString();

        $pending = AffiliateCommission::whereIn('affiliate_id', $affiliates->getCollection()->pluck('id'))
            ->where('status', 'pending')
            ->selectRaw('affiliate_id, currency, SUM(commission_cents) AS total')
            ->groupBy('affiliate_id', 'currency')->get()->groupBy('affiliate_id');

        return view('admin.affiliates.index', ['affiliates' => $affiliates, 'pending' => $pending]);
    }

    public function create(): View
    {
        return view('admin.affiliates.form', ['affiliate' => new User()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $affiliate = DB::transaction(function () use ($data, $request) {
            $user = new User(['name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null, 'password' => Str::password(40)]);
            $user->role = Role::Affiliate;
            $user->affiliate_data = $data['affiliate_data'] ?? null;
            $user->save();
            Audit::record($request->user(), 'affiliate.create', $user);

            return $user;
        });

        Invitations::send($affiliate);

        return redirect()->route('admin.affiliates.show', $affiliate)->with('status', 'Affiliate created. An invitation to set a password has been emailed.');
    }

    public function show(User $affiliate): View
    {
        $this->ensureAffiliate($affiliate);
        $since = now()->subDays(30);

        return view('admin.affiliates.show', [
            'affiliate' => $affiliate,
            'listings' => $affiliate->listings()->with(['island', 'translations'])
                ->withCount(['clicks as clicks_30' => fn ($q) => $q->where('is_bot', false)->where('clicked_at', '>=', $since)])
                ->get(),
            'commission' => AffiliateCommission::where('affiliate_id', $affiliate->id)
                ->selectRaw('status, currency, COUNT(*) AS n, SUM(commission_cents) AS total')
                ->groupBy('status', 'currency')->get(),
        ]);
    }

    public function update(Request $request, User $affiliate): RedirectResponse
    {
        $this->ensureAffiliate($affiliate);
        $data = $this->validated($request, $affiliate);

        $before = $affiliate->only(['name', 'email', 'phone']);
        $notesChanged = ($data['affiliate_data'] ?? null) !== $affiliate->affiliate_data;
        $affiliate->forceFill([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'affiliate_data' => $data['affiliate_data'] ?? null,
        ])->save();

        // The private notes are not copied into the audit log, only the fact they changed.
        Audit::record($request->user(), 'affiliate.update', $affiliate, null, [
            'before' => $before, 'after' => $affiliate->only(['name', 'email', 'phone']), 'notes_changed' => $notesChanged,
        ]);

        return back()->with('status', 'Saved.');
    }

    /** Deactivating an affiliate pauses all of their listings. */
    public function deactivate(Request $request, User $affiliate, AccountStatus $accounts): RedirectResponse
    {
        $this->ensureAffiliate($affiliate);
        $accounts->deactivate($affiliate, $request->user(), $this->reason($request));

        return back()->with('status', 'Affiliate deactivated and their listings paused.');
    }

    public function reactivate(Request $request, User $affiliate, AccountStatus $accounts): RedirectResponse
    {
        $this->ensureAffiliate($affiliate);
        $accounts->reactivate($affiliate, $request->user());

        return back()->with('status', 'Affiliate active again. Their listings stay paused until made live.');
    }

    public function invite(Request $request, User $affiliate): RedirectResponse
    {
        $this->ensureAffiliate($affiliate);
        Invitations::send($affiliate);
        Audit::record($request->user(), 'affiliate.invite', $affiliate);

        return back()->with('status', 'A new invitation has been emailed.');
    }

    private function validated(Request $request, ?User $affiliate = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($affiliate?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'affiliate_data' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    private function ensureAffiliate(User $user): void
    {
        abort_unless($user->isAffiliate(), 404);
    }
}
