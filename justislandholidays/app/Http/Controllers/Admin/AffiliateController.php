<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AccountStatus;
use App\Admin\Invitations;
use App\Enums\ListingStatus;
use App\Enums\Role;
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

        // No commission reports (not needed, 19 Nov 2026): affiliates earn through their own programmes.
        return view('admin.affiliates.index', ['affiliates' => $query->paginate(self::PER_PAGE)->withQueryString()]);
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

    /** Click periods offered on the affiliate page: value => label. */
    public const CLICK_PERIODS = ['30' => 'Last 30 days', '60' => 'Last 60 days', '365' => 'Last 365 days', 'all' => 'All time'];

    public function show(Request $request, User $affiliate): View
    {
        $this->ensureAffiliate($affiliate);

        $period = (string) $request->query('clicks', '30');
        $period = array_key_exists($period, self::CLICK_PERIODS) ? $period : '30';

        return view('admin.affiliates.show', [
            'affiliate' => $affiliate,
            'listings' => $this->withClicks($affiliate->listings()->with(['island', 'translations'])->getQuery(), $period)->get(),
            'period' => $period,
            'periods' => self::CLICK_PERIODS,
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

    /** Logo for the banner above Check availability: add, replace, change its description or remove. Audited. */
    public function updateLogo(Request $request, User $affiliate): RedirectResponse
    {
        $this->ensureAffiliate($affiliate);
        $before = $affiliate->only(['affiliate_logo_path', 'affiliate_logo_alt']);

        if ($request->boolean('remove')) {
            \App\Support\AffiliateLogo::delete($affiliate->affiliate_logo_path);
            $affiliate->forceFill(['affiliate_logo_path' => null, 'affiliate_logo_alt' => null])->save();
            Audit::record($request->user(), 'affiliate.logo_remove', $affiliate, null, ['before' => $before]);

            return back()->with('status', 'Logo removed. Their property pages show the placeholder.');
        }

        $request->validate([
            'logo' => [$affiliate->affiliate_logo_path ? 'nullable' : 'required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_height='.\App\Support\AffiliateLogo::MIN_HEIGHT],
            'logo_alt' => ['required', 'string', 'max:160'],
        ], [
            'logo.required' => 'Choose a logo file.',
            'logo.mimes' => 'The logo must be a PNG, JPG or WebP file.',
            'logo.max' => 'The logo must be 2 MB or smaller.',
            'logo.dimensions' => 'The logo must be at least '.\App\Support\AffiliateLogo::MIN_HEIGHT.' pixels tall.',
            'logo_alt.required' => 'Describe the logo for people who cannot see it, usually the company name.',
        ]);

        $path = $affiliate->affiliate_logo_path;
        if ($request->hasFile('logo')) {
            $path = \App\Support\AffiliateLogo::store($request->file('logo'));
            \App\Support\AffiliateLogo::delete($affiliate->affiliate_logo_path);
        }
        $affiliate->forceFill(['affiliate_logo_path' => $path, 'affiliate_logo_alt' => trim((string) $request->input('logo_alt'))])->save();
        Audit::record($request->user(), 'affiliate.logo_update', $affiliate, null, ['before' => $before, 'after' => $affiliate->only(['affiliate_logo_path', 'affiliate_logo_alt'])]);

        return back()->with('status', $request->hasFile('logo') ? 'Logo saved. It shows on their property pages now.' : 'Logo description saved.');
    }

    /**
     * Adds `clicks` (real visitors, no bots) for the period to each listing. Raw click events
     * are kept for 13 months, but daily totals for good, so older days come from
     * deeplink_clicks_daily and the last few days (which the nightly rollup may not have
     * reached yet) from the raw events. That keeps "All time" and 365 days complete.
     */
    private function withClicks(\Illuminate\Database\Eloquent\Builder $query, string $period): \Illuminate\Database\Eloquent\Builder
    {
        $recentFrom = now()->startOfDay()->subDays(max(1, (int) config('site.clicks.rollup_days')) - 1);
        $from = $period === 'all' ? null : now()->startOfDay()->subDays((int) $period - 1);

        $daily = DB::table('deeplink_clicks_daily')->selectRaw('COALESCE(SUM(clicks), 0)')
            ->whereColumn('deeplink_clicks_daily.listing_id', 'listings.id')
            ->where('day', '<', $recentFrom->toDateString())
            ->when($from, fn ($q) => $q->where('day', '>=', $from->toDateString()));

        $raw = DeeplinkClick::selectRaw('COUNT(*)')
            ->whereColumn('deeplink_clicks.listing_id', 'listings.id')
            ->where('is_bot', false)
            ->where('clicked_at', '>=', $from && $from->gt($recentFrom) ? $from : $recentFrom);

        return $query->select('listings.*')
            ->selectSub($daily, 'clicks_daily')
            ->selectSub($raw, 'clicks_recent');
    }

    private function ensureAffiliate(User $user): void
    {
        abort_unless($user->isAffiliate(), 404);
    }
}
