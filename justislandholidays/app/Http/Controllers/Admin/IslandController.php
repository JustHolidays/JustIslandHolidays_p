<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ListingStatus;
use App\Models\Area;
use App\Models\Island;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Islands and resorts. Island admins edit the introduction text for their islands and
 * resorts. Super admins also create, rename, reorder, set the map centre and switch islands
 * on or off. A slug cannot change once the place has live listings, to protect URLs.
 */
class IslandController extends AdminController
{
    private const LANGS = ['en' => 'English', 'es' => 'Spanish', 'de' => 'German'];

    public function index(): View
    {
        $islands = $this->withCounts($this->ctx()->scope(Island::query(), 'id'))->orderBy('sort_order')->get();

        return view('admin.islands.index', ['islands' => $islands, 'single' => null]);
    }

    /** One island and its resorts, from the Islands and resorts menu. Another island's admin gets 403. */
    public function show(Island $island): View
    {
        $this->ctx()->authorizeIsland($island->id);

        $island = $this->withCounts(Island::whereKey($island->id))->sole();

        return view('admin.islands.index', ['islands' => collect([$island]), 'single' => $island]);
    }

    /** Add a location (super admins): a new island, or a resort or town on an existing one. */
    public function createLocation(Request $request): View
    {
        $islands = Island::orderBy('sort_order')->get();
        $chosen = $islands->firstWhere('id', (int) $request->query('island'));

        return view('admin.islands.create', ['islands' => $islands, 'chosen' => $chosen]);
    }

    /** The resort form on Add a location, where the island is chosen from a list. */
    public function storeLocationArea(Request $request): RedirectResponse
    {
        $request->validate(['island' => ['required', 'integer', 'exists:islands,id']], ['island.required' => 'Choose the island.']);

        return $this->storeArea($request, Island::findOrFail((int) $request->input('island')));
    }

    private function withCounts($query)
    {
        $live = fn ($q) => $q->where('status', ListingStatus::Live);

        return $query->withCount(['listings as live_count' => $live])
            ->with(['areas' => fn ($q) => $q->withCount(['listings as live_count' => $live])->orderBy('sort_order')]);
    }

    public function editIsland(Island $island): View
    {
        $this->authorizeText($island);

        return view('admin.islands.edit', ['place' => $island, 'kind' => 'island', 'langs' => self::LANGS, 'slugLocked' => $this->hasLive($island)]);
    }

    public function editArea(Area $area): View
    {
        $this->authorizeText($area);

        return view('admin.islands.edit', ['place' => $area->load('island'), 'kind' => 'area', 'langs' => self::LANGS, 'slugLocked' => $this->hasLive($area)]);
    }

    public function updateIsland(Request $request, Island $island): RedirectResponse
    {
        $this->authorizeText($island);
        $this->save($request, $island, 'island');

        return redirect()->route('admin.islands.index')->with('status', 'Saved.');
    }

    public function updateArea(Request $request, Area $area): RedirectResponse
    {
        $this->authorizeText($area);
        $this->save($request, $area, 'area');

        return redirect()->route('admin.islands.index')->with('status', 'Saved.');
    }

    public function storeIsland(Request $request): RedirectResponse
    {
        // On Add a location the island form has its own field names, so its errors
        // do not also show on the resort form beside it.
        $data = $request->validate([
            'island_slug' => ['required', 'regex:/^[a-z0-9-]{2,60}$/', 'unique:islands,slug'],
            'island_name' => ['required', 'string', 'max:80'],
        ], [
            'island_slug.regex' => 'Use lower case letters, numbers and hyphens only.',
            'island_slug.unique' => 'There is already an island with this web address part.',
        ], ['island_slug' => 'web address part', 'island_name' => 'name']);

        $island = Island::create([
            'slug' => $data['island_slug'],
            'name' => ['en' => $data['island_name'], 'es' => $data['island_name'], 'de' => $data['island_name']],
            'is_active' => false,
            'sort_order' => (int) Island::max('sort_order') + 1,
        ]);
        Audit::record($request->user(), 'island.create', $island);

        return redirect()->route('admin.islands.edit', $island)->with('status', 'Island created, switched off. Add its details, then switch it on.');
    }

    public function storeArea(Request $request, Island $island): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'regex:/^[a-z0-9-]{2,80}$/', Rule::unique('areas', 'slug')->where('island_id', $island->id)],
            'name_en' => ['required', 'string', 'max:80'],
            'kind' => ['required', Rule::in(['beach_resort', 'coastal_resort', 'town'])],
        ], ['slug.unique' => 'That island already has a resort or town with this web address part.']);

        $area = Area::create([
            'island_id' => $island->id,
            'slug' => $data['slug'],
            'kind' => $data['kind'],
            'name' => ['en' => $data['name_en'], 'es' => $data['name_en'], 'de' => $data['name_en']],
            'sort_order' => (int) $island->areas()->max('sort_order') + 1,
        ]);
        Audit::record($request->user(), 'area.create', $area);

        return redirect()->route('admin.areas.edit', $area)->with('status', 'Resort created.');
    }

    private function save(Request $request, Island|Area $place, string $kind): void
    {
        $isSuper = $request->user()->can('manage', $place);

        $rules = [];
        foreach (array_keys(self::LANGS) as $lang) {
            $rules["intro_{$lang}"] = ['nullable', 'string', 'max:20000'];
            if ($isSuper) {
                $rules["name_{$lang}"] = [$lang === 'en' ? 'required' : 'nullable', 'string', 'max:80'];
            }
        }
        if ($isSuper) {
            $table = $kind === 'island' ? 'islands' : 'areas';
            $unique = Rule::unique($table, 'slug')->ignore($place->id);
            if ($kind === 'area') {
                $unique->where('island_id', $place->island_id);
            }
            $rules += [
                'slug' => ['required', 'regex:/^[a-z0-9-]{2,80}$/', $unique],
                'centre_lat' => ['nullable', 'numeric', 'between:38.5,40.2'],
                'centre_lng' => ['nullable', 'numeric', 'between:1.1,4.5'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            ];
            if ($kind === 'island') {
                $rules['is_active'] = ['nullable', 'boolean'];
            } else {
                $rules['kind'] = ['required', Rule::in(['beach_resort', 'coastal_resort', 'town'])];
            }
        }

        $data = $request->validate($rules);
        $before = $place->only(['name', 'intro', 'slug', 'centre_lat', 'centre_lng', 'sort_order', 'is_active', 'kind']);

        $intro = [];
        foreach (array_keys(self::LANGS) as $lang) {
            $value = trim((string) ($data["intro_{$lang}"] ?? ''));
            if ($value !== '') {
                $intro[$lang] = $value;
            }
        }
        $place->intro = $intro ?: null;

        if ($isSuper) {
            if ($data['slug'] !== $place->slug && $this->hasLive($place)) {
                throw ValidationException::withMessages(['slug' => 'The web address cannot change while there are live listings here.']);
            }
            $names = [];
            foreach (array_keys(self::LANGS) as $lang) {
                $names[$lang] = trim((string) ($data["name_{$lang}"] ?? '')) ?: $data['name_en'];
            }
            $place->forceFill([
                'name' => $names,
                'slug' => $data['slug'],
                'centre_lat' => $data['centre_lat'] ?? null,
                'centre_lng' => $data['centre_lng'] ?? null,
                'sort_order' => $data['sort_order'] ?? $place->sort_order,
            ]);
            if ($kind === 'island') {
                $place->is_active = (bool) ($data['is_active'] ?? false);
            } else {
                $place->kind = $data['kind'];
            }
        }

        $place->save();

        Audit::record($request->user(), $kind.'.update', $place, null, [
            'before' => $before,
            'after' => $place->only(['name', 'intro', 'slug', 'centre_lat', 'centre_lng', 'sort_order', 'is_active', 'kind']),
        ]);
    }

    private function authorizeText(Model $place): void
    {
        $this->ctx()->authorizeIsland($place instanceof Island ? $place->id : $place->island_id);
        abort_unless(Gate::allows('updateText', $place), 403);
    }

    private function hasLive(Island|Area $place): bool
    {
        return $place->listings()->where('status', ListingStatus::Live)->exists();
    }
}
