<?php

namespace App\Http\Controllers\Admin;

use App\Models\Island;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Content pages: site-wide pages (about, terms, privacy) for super admins, and guides
 * tied to an island for that island's admins. One tab per language; Markdown with a preview.
 */
class PageController extends AdminController
{
    public const LANGS = ['en' => 'English', 'es' => 'Spanish', 'de' => 'German'];

    public function index(Request $request): View
    {
        $ctx = $this->ctx();
        $query = Page::query()->with(['island', 'translations:page_id,lang,title']);

        // Island admins see only guides on their islands; super admins also see site-wide pages.
        if ($ctx->admin()->isSuperAdmin() && ! $ctx->selectedIslandId()) {
            // everything
        } else {
            $ctx->scope($query);
        }

        if (filled($q = $request->query('q')) && is_string($q)) {
            $like = $this->like($q);
            $query->where(fn (Builder $w) => $w->where('slug', 'like', $like)->orWhereHas('translations', fn ($t) => $t->where('title', 'like', $like)));
        }

        $this->sort($query, $request, ['slug' => 'slug', 'updated' => 'updated_at'], 'updated');

        return view('admin.pages.index', ['pages' => $query->paginate(self::PER_PAGE)->withQueryString()]);
    }

    public function create(): View
    {
        return view('admin.pages.edit', ['page' => new Page(['kind' => 'guide']), 'langs' => self::LANGS, 'islands' => $this->ctx()->islands(), 'lang' => 'en']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        abort_unless(Gate::allows('create', [Page::class, $data['island_id']]), 403);

        $page = DB::transaction(function () use ($data, $request) {
            $page = Page::create(['slug' => $data['slug'], 'island_id' => $data['island_id'], 'kind' => $data['kind'], 'is_published' => (bool) ($data['is_published'] ?? false)]);
            $this->saveText($page, $data);
            Audit::record($request->user(), 'page.create', $page, null, ['slug' => $page->slug, 'island_id' => $page->island_id]);

            return $page;
        });

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page saved.');
    }

    public function edit(Request $request, Page $page): View
    {
        $lang = array_key_exists($request->query('lang'), self::LANGS) ? $request->query('lang') : 'en';

        return view('admin.pages.edit', ['page' => $page->load('translations'), 'langs' => self::LANGS, 'islands' => $this->ctx()->islands(), 'lang' => $lang]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $data = $this->validated($request, $page);
        // Moving a page to another island needs permission for that island too.
        abort_unless(Gate::allows('create', [Page::class, $data['island_id']]), 403);

        DB::transaction(function () use ($page, $data, $request) {
            $before = $page->only(['slug', 'island_id', 'kind', 'is_published']);
            $page->update(['slug' => $data['slug'], 'island_id' => $data['island_id'], 'kind' => $data['kind'], 'is_published' => (bool) ($data['is_published'] ?? false)]);
            $this->saveText($page, $data);
            Audit::record($request->user(), 'page.update', $page, null, ['before' => $before, 'after' => $page->only(['slug', 'island_id', 'kind', 'is_published']), 'lang' => $data['lang']]);
        });

        return redirect()->route('admin.pages.edit', [$page, 'lang' => $data['lang']])->with('status', 'Page saved.');
    }

    /** Live preview: the same Markdown rendering the public page uses (HTML is stripped). */
    public function preview(Request $request): JsonResponse
    {
        $md = (string) $request->validate(['body' => ['nullable', 'string', 'max:200000']])['body'];

        return response()->json(['html' => Str::markdown($md, ['html_input' => 'strip', 'allow_unsafe_links' => false])]);
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        $islandId = $request->filled('island_id') ? $request->integer('island_id') : null;

        $data = $request->validate([
            'slug' => ['required', 'regex:/^[a-z0-9-]{2,120}$/'],
            'island_id' => ['nullable', 'integer', 'exists:islands,id'],
            'kind' => ['required', Rule::in(['static', 'guide'])],
            'is_published' => ['nullable', 'boolean'],
            'lang' => ['required', Rule::in(array_keys(self::LANGS))],
            'title' => ['required', 'string', 'max:190'],
            'body_md' => ['required', 'string', 'max:200000'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:300'],
        ], ['slug.regex' => 'Use lower case letters, numbers and hyphens only.']);

        $taken = Page::where('slug', $data['slug'])
            ->where(fn ($q) => $islandId ? $q->where('island_id', $islandId) : $q->whereNull('island_id'))
            ->when($page, fn ($q) => $q->whereKeyNot($page->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['slug' => 'Another page already uses this web address.']);
        }

        return $data + ['island_id' => $islandId];
    }

    private function saveText(Page $page, array $data): void
    {
        PageTranslation::query()->updateOrInsert(
            ['page_id' => $page->id, 'lang' => $data['lang']],
            ['title' => $data['title'], 'body_md' => $data['body_md'], 'meta_title' => $data['meta_title'] ?? null, 'meta_description' => $data['meta_description'] ?? null],
        );
    }
}
