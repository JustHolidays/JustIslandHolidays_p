<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\HeroImage;
use App\Models\SiteSetting;
use App\Support\Appearance;
use App\Support\Audit;
use GdImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Appearance (super admins only): colours, logo, favicon, home page hero images and map markers.
 * Everything is saved as a draft and goes live only on Publish. Every publish is audited
 * with the previous values, so the last publish can be undone.
 *
 * Logo and favicon accept PNG only for now: an SVG served from our own domain could carry
 * script, so SVG needs sanitising before it can be allowed.
 */
class AppearanceController extends AdminController
{
    private const KEYS = ['theme.colours', 'brand.logo', 'brand.favicon', 'home.hero_mode', 'home.hero', 'map.markers', 'footer.image'];

    private const HERO_WIDTHS = [800, 1600, 2400];

    public function edit(): View
    {
        $draftColours = Appearance::colours(true);

        return view('admin.appearance.edit', [
            'colours' => $draftColours,
            'published' => Appearance::colours(false),
            'checks' => Appearance::contrastChecks($draftColours),
            'canPublish' => Appearance::canPublishColours($draftColours) && Appearance::canPublishMarkers(Appearance::markers(true)),
            'markers' => Appearance::markers(true),
            'publishedMarkers' => Appearance::markers(false),
            'logo' => Appearance::get('brand.logo', true),
            'logoUrl' => Appearance::logo(true)['url'] ?? null,
            'favicons' => Appearance::favicons(true),
            'footerImage' => Appearance::footerImage(true),
            'heroMode' => Appearance::heroMode(true),
            'heroes' => HeroImage::orderBy('sort_order')->orderBy('id')->get(),
            'heroDraft' => collect($this->heroArrangement())->keyBy('id'),
            'hasDraft' => SiteSetting::whereIn('setting_key', self::KEYS)->whereNotNull('draft_value')->exists(),
            'lastPublish' => AuditLog::with('actor')->where('action', 'appearance.publish')->latest('id')->first(),
        ]);
    }

    public function saveColours(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (array_keys(Appearance::COLOUR_NAMES) as $name) {
            $rules[$name] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }
        $data = $request->has('reset')
            ? config('site.default_colours')
            : array_map('strtolower', $request->validate($rules, ['*.regex' => 'Use a colour code like #0b4f6c.']));

        $this->saveDraft('theme.colours', $data, $request);

        return back()->with('status', $request->has('reset') ? 'Original colours restored in the draft. Publish to make it live.' : 'Colours saved as a draft.');
    }

    public function saveLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['nullable', 'file', 'mimes:png', 'max:1024', 'dimensions:min_height=40'],
            'alt' => ['required_with:logo', 'nullable', 'string', 'max:160'],
            'remove' => ['nullable', 'boolean'],
        ], ['logo.mimes' => 'The logo must be a PNG file.', 'alt.required_with' => 'Describe the logo for people who cannot see it (usually the company name).']);

        if ($request->boolean('remove')) {
            $this->saveDraft('brand.logo', [], $request);

            return back()->with('status', 'Logo removed in the draft. The brand name will show as text.');
        }

        $current = Appearance::get('brand.logo', true) ?? [];
        $path = $request->hasFile('logo')
            ? $this->storePng($request->file('logo'), 'brand/logo-'.Str::uuid().'.png', maxHeight: 160)
            : ($current['path'] ?? null);

        if (! $path) {
            throw ValidationException::withMessages(['logo' => 'Choose a logo file.']);
        }

        $this->saveDraft('brand.logo', ['path' => $path, 'alt' => $request->input('alt', $current['alt'] ?? 'Just Island Holidays')], $request);

        return back()->with('status', 'Logo saved as a draft.');
    }

    public function saveFavicon(Request $request): RedirectResponse
    {
        $request->validate(['favicon' => ['required', 'file', 'mimes:png', 'max:1024', 'dimensions:min_width=512,ratio=1']], [
            'favicon.dimensions' => 'The favicon must be square and at least 512 pixels wide.',
            'favicon.mimes' => 'The favicon must be a PNG file.',
        ]);

        $source = $this->gd($request->file('favicon'));
        $id = Str::uuid();
        $sizes = [];
        foreach ([32, 180, 192, 512] as $size) {
            $sizes[$size] = "brand/favicon-{$id}-{$size}.png";
            Storage::disk('public')->put($sizes[$size], $this->pngBytes($this->resize($source, $size, $size)), 'public');
        }

        $this->saveDraft('brand.favicon', ['sizes' => $sizes], $request);

        return back()->with('status', 'Favicon saved as a draft.');
    }

    /**
     * The image across the top of the footer on every public page. Saved as WebP copies
     * (like island header images) and published with everything else. Old copies are kept,
     * so Undo can put the previous image back.
     */
    public function saveFooterImage(Request $request): RedirectResponse
    {
        if ($request->boolean('remove')) {
            $this->saveDraft('footer.image', [], $request);

            return back()->with('status', 'Footer image removed in the draft. The placeholder shows until a new one is added.');
        }

        $current = Appearance::get('footer.image', true) ?? [];
        $request->validate([
            'footer_image' => [empty($current['path']) ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,webp', 'max:5120',
                'dimensions:min_width='.\App\Support\HeaderImage::MIN_WIDTH.',min_height='.Appearance::FOOTER_IMAGE_HEIGHT],
            'footer_image_alt' => ['required', 'string', 'max:160'],
        ], [
            'footer_image.required' => 'Choose an image file.',
            'footer_image.mimes' => 'The footer image must be a JPG or WebP file.',
            'footer_image.max' => 'The footer image must be 5 MB or smaller.',
            'footer_image.dimensions' => 'The footer image must be at least '.\App\Support\HeaderImage::MIN_WIDTH.' pixels wide and '.Appearance::FOOTER_IMAGE_HEIGHT.' pixels tall.',
            'footer_image_alt.required' => 'Describe the image for people who cannot see it.',
        ]);

        $path = $request->hasFile('footer_image')
            ? \App\Support\HeaderImage::store($request->file('footer_image'))
            : $current['path'];

        $this->saveDraft('footer.image', ['path' => $path, 'alt' => trim((string) $request->input('footer_image_alt'))], $request);

        return back()->with('status', 'Footer image saved as a draft. Publish to make it live.');
    }

    /**
     * One marker kind at a time (affiliate, free or paid): label, colour, and an optional
     * square PNG icon shown beside the price. Saved as a draft like everything else here.
     */
    public function saveMarker(Request $request, string $kind): RedirectResponse
    {
        abort_unless(array_key_exists($kind, config('site.map.markers')), 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:40'],
            'colour' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'file', 'mimes:png', 'max:512', 'dimensions:min_width=64,ratio=1'],
            'remove_icon' => ['nullable', 'boolean'],
        ], [
            'colour.regex' => 'Use a colour code like #0b4f6c.',
            'icon.mimes' => 'The icon must be a PNG file.',
            'icon.dimensions' => 'The icon must be square and at least 64 pixels wide.',
        ]);

        $all = Appearance::markers(true);
        $current = $all[$kind];

        $icon = $current['icon'];
        if ($request->boolean('remove_icon')) {
            $icon = null;
        }
        if ($request->hasFile('icon')) {
            $icon = "map/marker-{$kind}-".Str::uuid().'.png';
            Storage::disk('public')->put($icon, $this->pngBytes($this->resize($this->gd($request->file('icon')), 64, 64)), 'public');
        }

        $all[$kind] = ['label' => trim($data['label']), 'colour' => strtolower($data['colour']), 'icon' => $icon];
        $value = collect($all)->map(fn ($m) => ['label' => $m['label'], 'colour' => $m['colour'], 'icon' => $m['icon']])->all();

        $this->saveDraft('map.markers', $value, $request);

        $status = 'Marker saved as a draft.';
        if (Appearance::contrast('#ffffff', $value[$kind]['colour']) < 4.5) {
            $status .= ' The colour is too light for white price text, so publishing is blocked until it is darker.';
        }

        return back()->with('status', $status);
    }

    public function uploadHero(Request $request): RedirectResponse
    {
        $request->validate([
            'hero' => ['required', 'file', 'mimes:jpg,jpeg,webp', 'max:5120', 'dimensions:min_width=2400'],
            'alt_text' => ['required', 'string', 'max:160'],
        ], [
            'hero.dimensions' => 'Hero images must be at least 2400 pixels wide.',
            'hero.max' => 'Hero images must be 5 MB or smaller.',
            'alt_text.required' => 'Describe the image for people who cannot see it.',
        ]);

        $source = $this->gd($request->file('hero'));
        $base = 'hero/'.Str::uuid();
        foreach (self::HERO_WIDTHS as $w) {
            $h = (int) round(imagesy($source) * $w / imagesx($source));
            ob_start();
            imagewebp($this->resize($source, $w, $h), null, 80);
            Storage::disk('public')->put("{$base}-{$w}.webp", (string) ob_get_clean(), 'public');
        }

        // New uploads start switched off until published.
        $hero = HeroImage::create([
            'image_path' => "{$base}-2400.webp",
            'alt_text' => $request->input('alt_text'),
            'sort_order' => (int) HeroImage::max('sort_order') + 1,
            'is_active' => false,
            'uploaded_by' => $request->user()->id,
        ]);

        $items = $this->heroArrangement();
        $items[] = ['id' => $hero->id, 'active' => false, 'sort' => $hero->sort_order];
        $this->saveDraft('home.hero', ['items' => $items], $request);

        return back()->with('status', 'Image uploaded. Switch it on, then publish.');
    }

    public function arrangeHeroes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['random', 'first'])],
            'heroes' => ['array'],
            'heroes.*.sort' => ['nullable', 'integer', 'min:0', 'max:255'],
            'heroes.*.active' => ['nullable', 'boolean'],
            'heroes.*.alt_text' => ['required', 'string', 'max:160'],
        ]);

        $items = [];
        foreach (HeroImage::all() as $hero) {
            $row = $data['heroes'][$hero->id] ?? null;
            if (! $row) {
                continue;
            }
            $items[] = ['id' => $hero->id, 'active' => (bool) ($row['active'] ?? false), 'sort' => (int) ($row['sort'] ?? $hero->sort_order), 'alt_text' => $row['alt_text']];
        }

        if (collect($items)->where('active', true)->count() > 5) {
            throw ValidationException::withMessages(['heroes' => 'Up to five hero images can be switched on.']);
        }

        $this->saveDraft('home.hero', ['items' => $items], $request);
        $this->saveDraft('home.hero_mode', ['mode' => $data['mode']], $request);

        return back()->with('status', 'Hero images saved as a draft.');
    }

    public function removeHero(Request $request, HeroImage $hero): RedirectResponse
    {
        if ($hero->is_active) {
            return back()->withErrors(['hero' => 'Switch this image off and publish before removing it.']);
        }

        $items = collect($this->heroArrangement())->reject(fn ($i) => $i['id'] === $hero->id)->values()->all();
        $this->saveDraft('home.hero', ['items' => $items], $request);

        foreach (self::HERO_WIDTHS as $w) {
            Storage::disk('public')->delete(str_replace('-2400.webp', "-{$w}.webp", $hero->image_path));
        }
        Audit::record($request->user(), 'appearance.hero_remove', $hero, null, ['alt_text' => $hero->alt_text]);
        $hero->delete();

        return back()->with('status', 'Image removed.');
    }

    public function publish(Request $request): RedirectResponse
    {
        if (! Appearance::canPublishColours(Appearance::colours(true))) {
            return back()->withErrors(['colours' => 'Publishing is blocked: the text and button colours need a contrast of at least 4.5 to 1.']);
        }
        if (! Appearance::canPublishMarkers(Appearance::markers(true))) {
            return back()->withErrors(['markers' => 'Publishing is blocked: each map marker colour needs a contrast of at least 4.5 to 1 with its white price text.']);
        }

        $changes = DB::transaction(function () use ($request) {
            $changes = [];
            foreach (SiteSetting::whereIn('setting_key', self::KEYS)->whereNotNull('draft_value')->lockForUpdate()->get() as $row) {
                $changes[$row->setting_key] = ['before' => $row->value, 'after' => $row->draft_value];

                if ($row->setting_key === 'home.hero') {
                    $changes['home.hero']['before'] = ['items' => HeroImage::all()->map(fn ($h) => ['id' => $h->id, 'active' => $h->is_active, 'sort' => $h->sort_order, 'alt_text' => $h->alt_text])->all()];
                    $this->applyHeroes($row->draft_value['items'] ?? []);
                }

                $row->forceFill(['value' => $row->draft_value, 'draft_value' => null, 'updated_by' => $request->user()->id])->save();
            }

            return $changes;
        });

        if ($changes === []) {
            return back()->with('status', 'There were no draft changes to publish.');
        }

        Audit::record($request->user(), 'appearance.publish', 'site_setting', 'appearance', $changes);

        return back()->with('status', 'Published. The public site now uses these settings.');
    }

    public function discard(Request $request): RedirectResponse
    {
        SiteSetting::whereIn('setting_key', self::KEYS)->update(['draft_value' => null]);
        Audit::record($request->user(), 'appearance.discard', 'site_setting', 'appearance');

        return back()->with('status', 'Draft changes discarded.');
    }

    /** Puts back the values from before the last publish. */
    public function undo(Request $request): RedirectResponse
    {
        $last = AuditLog::where('action', 'appearance.publish')->latest('id')->first();
        if (! $last) {
            return back()->withErrors(['undo' => 'There is nothing to undo.']);
        }

        DB::transaction(function () use ($last, $request) {
            foreach ($last->detail ?? [] as $key => $change) {
                if ($key === 'home.hero') {
                    $this->applyHeroes($change['before']['items'] ?? []);
                }
                if ($change['before'] === null) {
                    SiteSetting::whereKey($key)->delete();
                } else {
                    SiteSetting::updateOrCreate(['setting_key' => $key], ['value' => $change['before'], 'draft_value' => null, 'updated_by' => $request->user()->id]);
                }
            }
            Audit::record($request->user(), 'appearance.undo', 'site_setting', 'appearance', ['undid_audit_id' => $last->id, 'restored' => collect($last->detail)->map->before->all()]);
        });

        return back()->with('status', 'The last publish has been undone.');
    }

    private function saveDraft(string $key, array $value, Request $request): void
    {
        $row = SiteSetting::find($key) ?? new SiteSetting(['setting_key' => $key, 'value' => $key === 'theme.colours' ? config('site.default_colours') : []]);
        $row->draft_value = $value;
        $row->updated_by = $request->user()->id;
        $row->save();

        Audit::record($request->user(), 'appearance.draft', 'site_setting', $key, ['draft' => $value]);
    }

    /** @return list<array{id: int, active: bool, sort: int}> */
    private function heroArrangement(): array
    {
        return SiteSetting::find('home.hero')?->draft_value['items']
            ?? HeroImage::orderBy('sort_order')->get()->map(fn ($h) => ['id' => $h->id, 'active' => $h->is_active, 'sort' => $h->sort_order, 'alt_text' => $h->alt_text])->all();
    }

    private function applyHeroes(array $items): void
    {
        foreach ($items as $item) {
            HeroImage::whereKey($item['id'])->update(array_filter([
                'is_active' => (bool) $item['active'],
                'sort_order' => (int) $item['sort'],
                'alt_text' => $item['alt_text'] ?? null,
            ], fn ($v) => $v !== null));
        }
    }

    private function storePng(UploadedFile $file, string $path, int $maxHeight): string
    {
        $img = $this->gd($file);
        $h = min($maxHeight, imagesy($img));
        $w = (int) round(imagesx($img) * $h / imagesy($img));
        Storage::disk('public')->put($path, $this->pngBytes($this->resize($img, $w, $h)), 'public');

        return $path;
    }

    private function gd(UploadedFile $file): GdImage
    {
        $img = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $img instanceof GdImage) {
            throw ValidationException::withMessages(['file' => 'The image could not be read.']);
        }

        return $img;
    }

    private function resize(GdImage $src, int $w, int $h): GdImage
    {
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));

        return $dst;
    }

    private function pngBytes(GdImage $img): string
    {
        ob_start();
        imagepng($img, null, 9);

        return (string) ob_get_clean();
    }
}
