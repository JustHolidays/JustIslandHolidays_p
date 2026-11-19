<?php

namespace App\Http\Controllers\Admin;

use App\Ads\IslandAdverts;
use App\Models\Ad;
use App\Models\AdSlot;
use App\Models\Advertiser;
use App\Models\Island;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Affiliate Adverts (/admin/ads), super admins only (can:manage-ads). Each advert belongs to
 * one island and is shown in the Menu Advert Section, the Sidebar Section or both (see
 * App\Ads\IslandAdverts). Every change is audited.
 */
class AdController extends AdminController
{
    public function index(Request $request): View
    {
        $islands = Island::orderBy('sort_order')->get();
        $slots = AdSlot::whereIn('code', IslandAdverts::SECTIONS)->get()->keyBy('id');

        $query = Ad::with(['advertiser', 'island'])->whereIn('slot_id', $slots->keys())
            ->addSelect(['clicks' => DB::table('ad_stats_daily')->selectRaw('COALESCE(SUM(clicks), 0)')->whereColumn('ad_id', 'ads.id')]);
        if ($chosen = $islands->firstWhere('id', (int) $request->query('island'))) {
            $query->where('island_id', $chosen->id);
        }

        return view('admin.ads.index', [
            'islands' => $islands,
            'chosen' => $chosen,
            'ads' => $query->orderBy('island_id')->orderBy('slot_id')->orderByDesc('id')->get(),
            'sections' => $slots->mapWithKeys(fn ($s) => [$s->id => IslandAdverts::SECTION_LABELS[array_search($s->code, IslandAdverts::SECTIONS, true)]]),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form(new Ad([
            'island_id' => (int) $request->query('island') ?: null,
            'status' => 'active',
            'weight' => 1,
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addYear()->startOfDay(),
        ]), (string) $request->query('section', 'menu'));
    }

    public function store(Request $request): RedirectResponse
    {
        $ad = new Ad();
        $this->save($request, $ad);
        Audit::record($request->user(), 'ad.create', $ad, null, ['after' => $this->snapshot($ad)]);

        return redirect()->route('admin.ads.index', ['island' => $ad->island_id])->with('status', 'Advert added. It shows on the island’s pages while it is active and within its dates.');
    }

    public function edit(Ad $ad): View
    {
        $this->ensureIslandAdvert($ad);

        return $this->form($ad->load('advertiser'), IslandAdverts::sectionOf($ad) ?? 'menu');
    }

    public function update(Request $request, Ad $ad): RedirectResponse
    {
        $this->ensureIslandAdvert($ad);
        $before = $this->snapshot($ad);
        $this->save($request, $ad);
        Audit::record($request->user(), 'ad.update', $ad, null, ['before' => $before, 'after' => $this->snapshot($ad)]);

        return redirect()->route('admin.ads.index', ['island' => $ad->island_id])->with('status', 'Advert saved.');
    }

    public function destroy(Request $request, Ad $ad): RedirectResponse
    {
        $this->ensureIslandAdvert($ad);
        $before = $this->snapshot($ad);
        IslandAdverts::deleteImage($ad->image_path);
        $island = $ad->island_id;
        $ad->delete();
        Audit::record($request->user(), 'ad.delete', 'ad', (string) $before['id'], ['before' => $before]);

        return redirect()->route('admin.ads.index', ['island' => $island])->with('status', 'Advert deleted.');
    }

    private function form(Ad $ad, string $section): View
    {
        return view('admin.ads.form', [
            'ad' => $ad,
            'section' => array_key_exists($section, IslandAdverts::SECTIONS) ? $section : 'menu',
            'islands' => Island::orderBy('sort_order')->get(),
            'advertisers' => Advertiser::orderBy('name')->pluck('name'),
        ]);
    }

    private function save(Request $request, Ad $ad): void
    {
        $data = $request->validate([
            'island_id' => ['required', 'integer', 'exists:islands,id'],
            'section' => ['required', Rule::in(array_keys(IslandAdverts::SECTIONS))],
            'advertiser' => ['required', 'string', 'max:160'],
            'image' => [$ad->exists ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width='.IslandAdverts::WIDTH.',min_height='.IslandAdverts::HEIGHT],
            'alt' => ['required', 'string', 'max:190'],
            'target_url' => ['required', 'url:https', 'max:500'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in(['active', 'paused'])],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'island_id.required' => 'Choose the island this advert is for.',
            'image.required' => 'Choose an image for the advert.',
            'image.mimes' => 'The image must be a JPG, PNG or WebP file.',
            'image.dimensions' => 'The image must be at least '.IslandAdverts::WIDTH.' × '.IslandAdverts::HEIGHT.' pixels.',
            'alt.required' => 'Describe the advert for people who cannot see it, for example the business name and offer.',
            'target_url.url' => 'The link must be a full web address starting with https://',
            'ends_at.after' => 'The end date must be after the start date.',
        ]);

        $slot = AdSlot::where('code', IslandAdverts::SECTIONS[$data['section']])->firstOrFail();
        $advertiser = Advertiser::firstOrCreate(['name' => trim($data['advertiser'])]);

        if ($request->hasFile('image')) {
            $old = $ad->image_path;
            $ad->image_path = IslandAdverts::storeImage($request->file('image'));
            IslandAdverts::deleteImage($old);
        }

        $ad->forceFill([
            'advertiser_id' => $advertiser->id,
            'slot_id' => $slot->id,
            'island_id' => (int) $data['island_id'],
            'alt' => trim($data['alt']),
            'target_url' => $data['target_url'],
            'starts_at' => Carbon::parse($data['starts_at'])->startOfDay(),
            'ends_at' => Carbon::parse($data['ends_at'])->startOfDay(),
            'status' => $data['status'],
            'weight' => (int) $data['weight'],
        ])->save();
    }

    /** Only adverts in the sections are managed here, and always with an island. */
    private function ensureIslandAdvert(Ad $ad): void
    {
        abort_unless($ad->island_id && in_array($ad->slot?->code, IslandAdverts::SECTIONS, true), 404);
    }

    private function snapshot(Ad $ad): array
    {
        return $ad->only(['id', 'advertiser_id', 'slot_id', 'island_id', 'image_path', 'alt', 'target_url', 'status', 'weight']) + [
            'starts_at' => $ad->starts_at?->toDateString(),
            'ends_at' => $ad->ends_at?->toDateString(),
        ];
    }
}
