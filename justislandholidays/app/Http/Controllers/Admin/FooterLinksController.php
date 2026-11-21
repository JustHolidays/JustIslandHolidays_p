<?php

namespace App\Http\Controllers\Admin;

use App\Models\Page;
use App\Support\Audit;
use App\Support\FooterLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Settings › Footer links (super admins only, 21 Nov 2026): the labels, order and targets of the
 * links in the public footer. Each row goes to a site-wide content page, a built-in page or
 * another website. Rows are kept in the order of their Position numbers; ticked or empty rows
 * are dropped. "Use the standard links" puts back the original set.
 */
class FooterLinksController extends AdminController
{
    /** Blank rows offered for new links. */
    private const SPARE_ROWS = 3;

    public function edit(): View
    {
        $rows = FooterLinks::saved();
        for ($i = 0; $i < self::SPARE_ROWS && count($rows) < FooterLinks::MAX_LINKS; $i++) {
            $rows[] = ['type' => '', 'target' => '', 'label' => []];
        }

        return view('admin.footer-links', [
            'rows' => $rows,
            'pages' => $this->sitePages(),
            'languages' => config('site.enabled_languages', ['en']),
            'customised' => FooterLinks::isCustomised(),
            'preview' => FooterLinks::forFooter('en'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if ($request->boolean('reset')) {
            FooterLinks::save(null, $request->user()->id);
            Audit::record($request->user(), 'site.footer_links', 'site_setting', FooterLinks::KEY, ['reset' => true]);

            return back()->with('status', 'The footer has the standard links again.');
        }

        $languages = config('site.enabled_languages', ['en']);
        $request->validate([
            'links' => ['array', 'max:'.(FooterLinks::MAX_LINKS + self::SPARE_ROWS)],
            'links.*.goes_to' => ['nullable', 'string', 'max:100'],
            'links.*.url' => ['nullable', 'string', 'max:500'],
            'links.*.position' => ['nullable', 'integer', 'min:1', 'max:99'],
            'links.*.label' => ['nullable', 'array'],
            'links.*.label.*' => ['nullable', 'string', 'max:40'],
        ], ['links.*.label.*.max' => 'Footer labels can be up to 40 characters.']);

        $pages = array_keys($this->sitePages());
        $errors = [];
        $kept = [];
        foreach ((array) $request->input('links', []) as $i => $row) {
            $goesTo = trim((string) ($row['goes_to'] ?? ''));
            $labels = collect($languages)->mapWithKeys(fn ($lang) => [$lang => trim((string) ($row['label'][$lang] ?? ''))])->filter()->all();
            if (! empty($row['remove']) || ($goesTo === '' && $labels === [])) {
                continue;   // ticked to remove, or a spare row left empty
            }
            $line = $i + 1;
            if ($goesTo === '') {
                $errors["links.{$i}.goes_to"] = "Row {$line}: choose where the link goes.";

                continue;
            }
            if (($labels['en'] ?? '') === '') {
                $errors["links.{$i}.label.en"] = "Row {$line}: add a label in English.";

                continue;
            }

            [$type, $target] = array_pad(explode(':', $goesTo, 2), 2, '');
            if ($type === 'url') {
                $target = trim((string) ($row['url'] ?? ''));
                if (! filter_var($target, FILTER_VALIDATE_URL) || ! str_starts_with($target, 'https://')) {
                    $errors["links.{$i}.url"] = "Row {$line}: the web address must start with https://";

                    continue;
                }
            } elseif (! ($type === 'page' && in_array($target, $pages, true)) && ! ($type === 'builtin' && isset(FooterLinks::BUILT_IN[$target]))) {
                $errors["links.{$i}.goes_to"] = "Row {$line}: choose a page from the list.";

                continue;
            }

            $kept[] = ['position' => (int) ($row['position'] ?? 0) ?: 100 + $i, 'i' => $i, 'link' => ['type' => $type, 'target' => $target, 'label' => $labels]];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        if (count($kept) > FooterLinks::MAX_LINKS) {
            throw ValidationException::withMessages(['links' => 'The footer can have up to '.FooterLinks::MAX_LINKS.' links.']);
        }
        if ($kept === []) {
            throw ValidationException::withMessages(['links' => 'Keep at least one link, or use the standard links.']);
        }

        usort($kept, fn ($a, $b) => [$a['position'], $a['i']] <=> [$b['position'], $b['i']]);
        $links = array_column($kept, 'link');
        $before = FooterLinks::saved();
        FooterLinks::save($links, $request->user()->id);
        Audit::record($request->user(), 'site.footer_links', 'site_setting', FooterLinks::KEY, ['before' => $before, 'after' => $links]);

        return redirect()->route('admin.footer-links.edit')->with('status', 'Footer links saved. Every public page shows them now.');
    }

    /** Site-wide content pages, slug => title (with "not published" where it applies). @return array<string, string> */
    private function sitePages(): array
    {
        return Page::whereNull('island_id')->with('translations:page_id,lang,title')->orderBy('slug')->get()
            ->mapWithKeys(fn (Page $p) => [$p->slug => ($p->translations->firstWhere('lang', 'en')?->title ?? $p->slug).($p->is_published ? '' : ' (not published)')])
            ->all();
    }
}
