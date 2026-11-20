<?php

namespace App\Http\Controllers\Admin;

use App\Support\Audit;
use App\Support\Maintenance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Settings › Maintenance mode (super admins only, 20 Nov 2026). */
class MaintenanceController extends AdminController
{
    public function edit(): View
    {
        return view('admin.maintenance', ['settings' => Maintenance::settings()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'on' => ['required', 'boolean'],
            'title' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:1500'],
            'until' => ['nullable', 'date', 'after:now'],
        ], [
            'until.after' => 'The "back by" time must be in the future, or left empty.',
        ]);
        $on = $request->boolean('on');
        $was = Maintenance::isOn();

        Maintenance::save(['on' => $on, 'title' => $data['title'] ?? null, 'message' => $data['message'] ?? null, 'until' => $data['until'] ?? null], $request->user()->id);
        Audit::record($request->user(), 'site.maintenance', 'site_setting', Maintenance::KEY, ['on' => $on, 'was' => $was, 'until' => $data['until'] ?? null]);

        return back()->with('status', match (true) {
            $on && ! $was => 'Maintenance mode is on. Visitors now see the maintenance page; admins still see the site.',
            ! $on && $was => 'Maintenance mode is off. The site is open to visitors again.',
            default => 'Saved.',
        });
    }
}
