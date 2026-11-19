<?php

namespace App\Http\Controllers\Public;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Partners;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Our partners (/{lang}/partners), and /go/partner/{id}, which counts a logo click and sends the visitor on. */
class PartnerController extends Controller
{
    public function index(string $lang): View
    {
        return view('public.partners', ['partners' => Partners::active()]);
    }

    /** Stateless (no session or cookies); stores only a daily total. */
    public function go(string $id): RedirectResponse
    {
        $affiliate = User::where('role', Role::Affiliate->value)->whereNull('deactivated_at')->find((int) $id);

        if (! $affiliate || ! Partners::hasWebsite($affiliate)) {
            return redirect()->route('partners', ['lang' => 'en']);
        }

        Partners::countClick($affiliate);

        return redirect()->away($affiliate->affiliate_website_url)->withHeaders(['Referrer-Policy' => 'strict-origin-when-cross-origin']);
    }
}
