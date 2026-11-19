<?php

namespace App\Http\Controllers\Admin;

use App\Models\Listing;
use App\Models\PropertyType;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Property types (/admin/property-types), super admins only (can:manage-property-types).
 * Add a type, rename it in each language, change the order, switch it on or off.
 * A type with listings cannot be deleted, only switched off. Every change is audited.
 */
class PropertyTypeController extends AdminController
{
    private const LANGS = ['en' => 'English', 'es' => 'Spanish', 'de' => 'German'];

    public function index(): View
    {
        $counts = Listing::withTrashed()->selectRaw('property_type, COUNT(*) as n')->groupBy('property_type')->pluck('n', 'property_type');

        return view('admin.property-types.index', [
            'types' => PropertyType::orderBy('sort_order')->orderBy('id')->get(),
            'counts' => $counts,
            'langs' => self::LANGS,
        ]);
    }

    public function edit(PropertyType $type): View
    {
        return view('admin.property-types.edit', [
            'type' => $type,
            'used' => Listing::withTrashed()->where('property_type', $type->code)->count(),
            'langs' => self::LANGS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'new_name_en' => ['required', 'string', 'max:60'],
            'new_code' => ['required', 'regex:/^[a-z][a-z0-9_]{1,29}$/', 'unique:property_types,code'],
        ], [
            'new_code.regex' => 'Use lower case letters, numbers and underscores, starting with a letter, for example country_house.',
            'new_code.unique' => 'There is already a type with this code.',
        ], ['new_name_en' => 'name', 'new_code' => 'code']);

        $name = $data['new_name_en'];
        $type = PropertyType::create([
            'code' => $data['new_code'],
            'name' => ['en' => $name, 'es' => $name, 'de' => $name],
            'sort_order' => (int) PropertyType::max('sort_order') + 1,
            'is_active' => true,
        ]);
        Audit::record($request->user(), 'property_type.create', $type, null, ['after' => $type->only(['code', 'name', 'sort_order', 'is_active'])]);

        return redirect()->route('admin.property-types.index')->with('status', "Added “{$name}”. Owners can choose it now. Add the Spanish and German names when you have them.");
    }

    public function update(Request $request, PropertyType $type): RedirectResponse
    {
        $rules = ['sort_order' => ['required', 'integer', 'min:0', 'max:1000'], 'is_active' => ['nullable', 'boolean']];
        foreach (array_keys(self::LANGS) as $lang) {
            $rules["name_{$lang}"] = [$lang === 'en' ? 'required' : 'nullable', 'string', 'max:60'];
        }
        $data = $request->validate($rules);

        $before = $type->only(['name', 'sort_order', 'is_active']);
        $names = [];
        foreach (array_keys(self::LANGS) as $lang) {
            $names[$lang] = trim((string) ($data["name_{$lang}"] ?? '')) ?: trim($data['name_en']);
        }
        $type->update([
            'name' => $names,
            'sort_order' => (int) $data['sort_order'],
            'is_active' => $request->boolean('is_active'),
        ]);
        Audit::record($request->user(), 'property_type.update', $type, null, ['before' => $before, 'after' => $type->only(['name', 'sort_order', 'is_active'])]);

        return redirect()->route('admin.property-types.index')->with('status', "Saved “{$names['en']}”.".($type->is_active ? '' : ' It is switched off: listings that use it keep it, but it is not offered for new listings or in search.'));
    }

    public function destroy(Request $request, PropertyType $type): RedirectResponse
    {
        if (Listing::withTrashed()->where('property_type', $type->code)->exists()) {
            return back()->withErrors(['type' => "“{$type->translate('name', 'en')}” is used by listings, so it cannot be deleted. Switch it off instead."]);
        }
        if (PropertyType::count() <= 1) {
            return back()->withErrors(['type' => 'There must be at least one property type.']);
        }

        $before = $type->only(['id', 'code', 'name', 'sort_order', 'is_active']);
        $type->delete();
        Audit::record($request->user(), 'property_type.delete', 'property_type', (string) $before['id'], ['before' => $before]);

        return redirect()->route('admin.property-types.index')->with('status', "Deleted “{$before['name']['en']}”.");
    }
}
