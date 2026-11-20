<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ListingCsv;
use App\Enums\ListingStatus;
use App\Models\Island;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Listings › Import and export (super admins only, 20 Nov 2026): download listings as a CSV,
 * download a blank template, and upload a CSV that is checked and previewed before anything
 * is saved. The preview is kept for 30 minutes for the admin who uploaded it.
 */
class ListingCsvController extends AdminController
{
    public function index(): View
    {
        return view('admin.listings.csv', [
            'islands' => Island::orderBy('sort_order')->get(),
            'statuses' => collect(ListingStatus::cases())->reject(fn ($s) => $s === ListingStatus::Archived),
            'columns' => ListingCsv::COLUMNS,
        ]);
    }

    public function export(Request $request, ListingCsv $csv): StreamedResponse
    {
        $data = $request->validate([
            'island' => ['nullable', 'integer', Rule::exists('islands', 'id')],
            'status' => ['nullable', Rule::in(array_map(fn ($s) => $s->value, ListingStatus::cases()))],
        ]);
        $query = $csv->exportQuery($data['island'] ?? null, $data['status'] ?? null);
        Audit::record($request->user(), 'listings.csv_export', 'listing', null, ['island' => $data['island'] ?? null, 'status' => $data['status'] ?? null, 'count' => $query->count()]);

        return $this->stream('listings-'.now()->format('Y-m-d').'.csv', function ($out) use ($query, $csv) {
            $query->chunk(200, function ($chunk) use ($out, $csv) {
                foreach ($chunk as $listing) {
                    fputcsv($out, array_map([ListingCsv::class, 'safeCell'], $csv->row($listing)), ',', '"', '');
                }
            });
        });
    }

    /** A blank file with the column names and one example row to copy. */
    public function template(): StreamedResponse
    {
        $example = [
            'ref' => '', 'status' => '', 'lister_email' => 'owner@example.com',
            'title' => 'Villa Example', 'summary' => 'A stone house with a pool, a short walk from the beach.',
            'island' => 'menorca', 'resort' => 'cala-galdana', 'property_type' => 'villa',
            'bedrooms' => 3, 'bathrooms' => 2, 'sleeps' => 6, 'pool' => 'private', 'pets' => 'not_stated',
            'beach_distance_m' => 400, 'from_price' => 1200, 'currency' => 'EUR',
            'latitude' => '39.9361', 'longitude' => '3.9634',
            'link_url' => '', 'link_type' => 'booking', 'licence_number' => 'ETV/1234',
        ];

        return $this->stream('listings-template.csv', fn ($out) => fputcsv($out, array_values($example), ',', '"', ''));
    }

    public function upload(Request $request, ListingCsv $csv): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']], [
            'file.required' => 'Choose a CSV file.',
            'file.mimes' => 'The file must be a CSV (in Excel: File, Save as, CSV UTF-8).',
            'file.max' => 'The file must be 2 MB or smaller.',
        ]);

        $read = $csv->read($request->file('file')->getRealPath());
        if ($read['error']) {
            return back()->withErrors(['file' => $read['error']]);
        }

        $token = Str::random(32);
        Cache::put($this->cacheKey($request, $token), ['rows' => $read['rows'], 'ignored' => $read['ignored'], 'name' => $request->file('file')->getClientOriginalName()], now()->addMinutes(30));

        return redirect()->route('admin.listings.csv.preview', $token);
    }

    public function preview(Request $request, string $token, ListingCsv $csv): View|RedirectResponse
    {
        $stored = Cache::get($this->cacheKey($request, $token));
        if (! $stored) {
            return redirect()->route('admin.listings.csv')->withErrors(['file' => 'That preview has expired. Upload the file again.']);
        }
        $checked = $csv->checkAll($stored['rows']);

        return view('admin.listings.csv-preview', [
            'token' => $token,
            'name' => $stored['name'],
            'ignored' => $stored['ignored'],
            'rows' => $checked,
            'counts' => [
                'add' => $checked->where('action', 'add')->where('errors', [])->count(),
                'update' => $checked->where('action', 'update')->where('errors', [])->count(),
                'unchanged' => $checked->where('action', 'unchanged')->count(),
                'errors' => $checked->filter(fn ($r) => $r['errors'] !== [])->count(),
            ],
        ]);
    }

    public function apply(Request $request, string $token, ListingCsv $csv): RedirectResponse
    {
        $key = $this->cacheKey($request, $token);
        $stored = Cache::pull($key);   // once only
        if (! $stored) {
            return redirect()->route('admin.listings.csv')->withErrors(['file' => 'That preview has expired or was already used. Upload the file again.']);
        }

        // Checked again now, in case anything changed since the preview.
        $result = $csv->apply($csv->checkAll($stored['rows']), $request->user());
        Audit::record($request->user(), 'listings.csv_import', 'listing', null, $result + ['file' => $stored['name']]);

        return redirect()->route('admin.listings.csv')->with('status', "Import done: {$result['added']} added as drafts, {$result['updated']} updated, {$result['skipped']} skipped (no changes or problems).");
    }

    private function cacheKey(Request $request, string $token): string
    {
        return 'listing-csv:'.$request->user()->id.':'.preg_replace('/[^A-Za-z0-9]/', '', $token);
    }

    private function stream(string $name, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // so Excel reads accents correctly
            fputcsv($out, array_keys(ListingCsv::COLUMNS), ',', '"', '');
            $rows($out);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
