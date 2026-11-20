<?php

namespace App\Http\Controllers\Admin;

use App\Models\Area;
use App\Models\Island;
use App\Support\Audit;
use App\Support\Seo;
use App\Support\SeoHealth;
use App\Support\Sitemap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Settings › SEO (super admins only, 20 Nov 2026): the sitemap, titles and descriptions for
 * each kind of page (and any one island or resort), the default sharing picture, the business
 * details search engines see, and a health check.
 */
class SeoController extends AdminController
{
    public function edit(): View
    {
        $langs = config('site.enabled_languages');
        $islands = Island::active()->orderBy('sort_order')->get();

        return view('admin.seo', [
            'langs' => $langs,
            'saved' => collect($langs)->mapWithKeys(fn ($l) => [$l => Seo::all($l)])->all(),
            'islands' => $islands,
            'areas' => Area::whereIn('island_id', $islands->pluck('id'))->orderBy('island_id')->orderBy('sort_order')->get()->groupBy('island_id'),
            'sitemap' => Sitemap::info(),
            'shareImage' => Seo::shareImageUrl(),
            'business' => config('site.business'),
            'logo' => \App\Support\Appearance::logo(),
            'blocked' => (bool) config('site.block_indexing'),
            'checks' => SeoHealth::checks(),
        ]);
    }

    /** Saves the titles and descriptions sent (empty fields go back to the built-in wording). */
    public function saveMeta(Request $request): RedirectResponse
    {
        $request->validate([
            'meta' => ['array'],
            'meta.*.*.title' => ['nullable', 'string', 'max:190'],
            'meta.*.*.description' => ['nullable', 'string', 'max:300'],
        ], [
            'meta.*.*.title.max' => 'Titles are up to 190 characters (about 60 show in search results).',
            'meta.*.*.description.max' => 'Descriptions are up to 300 characters (about 155 show in search results).',
        ]);

        $allowed = array_merge(
            array_keys(Seo::PAGES),
            Island::pluck('id')->map(fn ($id) => 'island:'.$id)->all(),
            Area::pluck('id')->map(fn ($id) => 'area:'.$id)->all(),
        );
        $langs = config('site.enabled_languages');
        $changed = [];
        foreach ((array) $request->input('meta', []) as $key => $byLang) {
            if (! in_array($key, $allowed, true)) {
                throw ValidationException::withMessages(['meta' => 'Unknown page.']);
            }
            foreach ((array) $byLang as $lang => $values) {
                if (! in_array($lang, $langs, true)) {
                    continue;
                }
                $before = Seo::all($lang)[$key] ?? ['title' => null, 'description' => null];
                $title = trim((string) ($values['title'] ?? '')) ?: null;
                $description = trim((string) ($values['description'] ?? '')) ?: null;
                if ($before['title'] !== $title || $before['description'] !== $description) {
                    Seo::save($key, $lang, $title, $description, $request->user()->id);
                    $changed[] = "{$key} ({$lang})";
                }
            }
        }
        if ($changed !== []) {
            Audit::record($request->user(), 'seo.meta', 'site_setting', 'seo', ['changed' => $changed]);
        }

        return redirect()->to(route('admin.seo.edit').'#titles')->with('status', $changed === [] ? 'Nothing changed.' : 'Saved: '.count($changed).' '.(count($changed) === 1 ? 'page' : 'pages').'. Changes show on the site straight away.');
    }

    public function saveShareImage(Request $request): RedirectResponse
    {
        if ($request->boolean('remove')) {
            Seo::setShareImage(null, $request->user()->id);
            Audit::record($request->user(), 'seo.share_image', 'site_setting', 'seo', ['removed' => true]);

            return redirect()->to(route('admin.seo.edit').'#sharing')->with('status', 'Default sharing picture removed.');
        }

        $request->validate(['share_image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=1200,min_height=630']], [
            'share_image.required' => 'Choose a picture.',
            'share_image.dimensions' => 'The picture must be at least 1200 pixels wide and 630 tall.',
            'share_image.mimes' => 'Use a JPG, PNG or WebP picture.',
        ]);
        Seo::setShareImage(Seo::storeShareImage($request->file('share_image')), $request->user()->id);
        Audit::record($request->user(), 'seo.share_image', 'site_setting', 'seo');

        return redirect()->to(route('admin.seo.edit').'#sharing')->with('status', 'Default sharing picture saved.');
    }

    public function rebuildSitemap(Request $request): RedirectResponse
    {
        Sitemap::forget();
        Sitemap::xml();
        Audit::record($request->user(), 'seo.sitemap_rebuild', 'site_setting', 'sitemap', Sitemap::info());

        return redirect()->to(route('admin.seo.edit').'#sitemap')->with('status', 'Sitemap rebuilt: '.Sitemap::info()['urls'].' addresses.');
    }
}
