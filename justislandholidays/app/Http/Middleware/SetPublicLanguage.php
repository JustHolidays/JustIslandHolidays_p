<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads {lang} from the URL. Languages that are not switched on yet return 404,
 * so /es and /de stay unindexed until phase 2.
 */
class SetPublicLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->route('lang');

        if (! in_array($lang, config('site.enabled_languages'), true)) {
            abort(404);
        }

        App::setLocale($lang);
        URL::defaults(['lang' => $lang]);

        return $next($request);
    }
}
