<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The island selector in the admin header. The choice is kept for the session. */
class IslandSelectorController extends AdminController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $value = $request->input('island');
        $this->ctx()->select(ctype_digit((string) $value) ? (int) $value : null);

        $back = url()->previous();

        return redirect()->to(str_starts_with($back, url('/admin')) ? $back : route('admin.dashboard'));
    }
}
