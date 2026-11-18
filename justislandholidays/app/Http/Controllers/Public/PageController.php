<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Plan;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PageController extends Controller
{
    /** Site-wide static pages (about, contact, terms, privacy), written in Markdown in the admin. */
    public function show(string $lang, string $slug): View
    {
        $page = Page::whereNull('island_id')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with('translations')
            ->firstOrFail();

        $translation = $page->translations->firstWhere('lang', $lang) ?? $page->translations->firstWhere('lang', 'en');
        abort_if($translation === null, 404);

        return view('public.page', [
            'page' => $page,
            'translation' => $translation,
            'html' => Str::markdown($translation->body_md, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'isFallback' => $translation->lang !== $lang,
        ]);
    }

    /** Owner information and plans. Prices come from the plans table; none are set until launch. */
    public function owners(string $lang): View
    {
        return view('public.owners', [
            'plans' => Plan::where('is_active', true)->orderBy('audience')->orderBy('search_boost')->get(),
        ]);
    }
}
