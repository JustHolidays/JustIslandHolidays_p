<?php

namespace App\Http\Controllers\Admin;

use App\Models\SiteSetting;
use App\Support\AdminTheme;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin Styler (/admin/admin-colours), super admins only (can:manage-appearance). */
class AdminColoursController extends AdminController
{
    public function edit(): View
    {
        $colours = AdminTheme::colours();

        return view('admin.admin-colours.edit', [
            'colours' => $colours,
            'checks' => AdminTheme::checks($colours),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if ($request->has('reset')) {
            $colours = AdminTheme::DEFAULTS;
        } else {
            $rules = [];
            foreach (array_keys(AdminTheme::DEFAULTS) as $name) {
                $rules[$name] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
            }
            $colours = array_map('strtolower', $request->validate($rules, ['*.regex' => 'Use a colour code like #0f172a.']));
        }

        if (! AdminTheme::canSave($colours)) {
            return back()->withInput()->withErrors(['colours' => 'Not saved: some text would be too hard to read. Each check needs a contrast of at least 4.5 to 1.']);
        }

        $before = AdminTheme::colours();
        SiteSetting::updateOrCreate(['setting_key' => AdminTheme::KEY], ['value' => $colours, 'updated_by' => $request->user()->id]);
        Audit::record($request->user(), 'admin_colours.update', 'site_setting', AdminTheme::KEY, ['before' => $before, 'after' => $colours]);

        return redirect()->route('admin.admin-colours.edit')->with('status', $request->has('reset') ? 'Original admin colours restored.' : 'Admin colours saved. They apply straight away for all staff.');
    }
}
