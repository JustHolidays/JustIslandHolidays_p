<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Sitemap;
use Illuminate\Http\Response;

/** /sitemap.xml for search engines (20 Nov 2026). Stateless: no session or cookies. */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        return response(Sitemap::xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
