<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/** robots.txt: everything blocked before launch (SITE_BLOCK_INDEXING), otherwise only private areas. */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $body = config('site.block_indexing')
            ? "User-agent: *\nDisallow: /\n"
            : "User-agent: *\nDisallow: /account\nDisallow: /admin\nDisallow: /go/\nDisallow: /altcha/\nDisallow: /api/\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
