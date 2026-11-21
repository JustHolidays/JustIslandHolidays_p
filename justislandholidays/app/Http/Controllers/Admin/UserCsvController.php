<?php

namespace App\Http\Controllers\Admin;

use App\Admin\UserCsv;
use App\Enums\Role;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owners and agents › Import and export (super admins only, 21 Nov 2026): download owner and
 * agent accounts as a CSV, download a blank template, and upload a CSV that is checked and
 * previewed before anything is saved. New accounts get no email; invitations are sent later
 * from each person's page. The preview is kept for 30 minutes for the admin who uploaded it.
 */
class UserCsvController extends AdminController
{
    public function index(): View
    {
        return view('admin.users.csv', [
            'columns' => UserCsv::COLUMNS,
            'exportOnly' => UserCsv::EXPORT_ONLY,
        ]);
    }

    public function export(Request $request, UserCsv $csv): StreamedResponse
    {
        $data = $request->validate([
            'role' => ['nullable', Rule::in([Role::Owner->value, Role::Agent->value])],
            'status' => ['nullable', Rule::in(['active', 'deactivated'])],
        ]);
        $query = $csv->exportQuery($data['role'] ?? null, $data['status'] ?? null);
        Audit::record($request->user(), 'users.csv_export', 'user', null, ['role' => $data['role'] ?? null, 'status' => $data['status'] ?? null, 'count' => $query->count()]);

        return $this->stream('owners-and-agents-'.now()->format('Y-m-d').'.csv', function ($out) use ($query, $csv) {
            $query->chunk(200, function ($chunk) use ($out, $csv) {
                foreach ($chunk as $user) {
                    fputcsv($out, $csv->row($user), ',', '"', '');
                }
            });
        });
    }

    /** A blank file with the column names and two example rows to replace. */
    public function template(): StreamedResponse
    {
        return $this->stream('owners-and-agents-template.csv', function ($out) {
            fputcsv($out, ['owner@example.com', 'Maria Example', 'owner', '+34 600 000 000', ''], ',', '"', '');
            fputcsv($out, ['agent@example.com', 'Tom Example', 'agent', '', ''], ',', '"', '');
        }, array_keys(UserCsv::COLUMNS));
    }

    public function upload(Request $request, UserCsv $csv): RedirectResponse
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

        return redirect()->route('admin.users.csv.preview', $token);
    }

    public function preview(Request $request, string $token, UserCsv $csv): View|RedirectResponse
    {
        $stored = Cache::get($this->cacheKey($request, $token));
        if (! $stored) {
            return redirect()->route('admin.users.csv')->withErrors(['file' => 'That preview has expired. Upload the file again.']);
        }
        $checked = $csv->checkAll($stored['rows']);

        return view('admin.users.csv-preview', [
            'token' => $token,
            'name' => $stored['name'],
            'ignored' => $stored['ignored'],
            'rows' => $checked,
            'counts' => [
                'add' => $checked->where('action', 'add')->where('errors', [])->count(),
                'update' => $checked->where('action', 'update')->where('errors', [])->count(),
                'unchanged' => $checked->where('action', 'unchanged')->where('errors', [])->count(),
                'errors' => $checked->filter(fn ($r) => $r['errors'] !== [])->count(),
            ],
        ]);
    }

    public function apply(Request $request, string $token, UserCsv $csv): RedirectResponse
    {
        $stored = Cache::pull($this->cacheKey($request, $token));   // once only
        if (! $stored) {
            return redirect()->route('admin.users.csv')->withErrors(['file' => 'That preview has expired or was already used. Upload the file again.']);
        }

        // Checked again now, in case anything changed since the preview.
        $result = $csv->apply($csv->checkAll($stored['rows']), $request->user());
        Audit::record($request->user(), 'users.csv_import', 'user', null, $result + ['file' => $stored['name']]);

        return redirect()->route('admin.users.csv')->with('status', "Import done: {$result['added']} accounts added, {$result['updated']} updated, {$result['skipped']} skipped (no changes or problems). No emails were sent.");
    }

    private function cacheKey(Request $request, string $token): string
    {
        return 'user-csv:'.$request->user()->id.':'.preg_replace('/[^A-Za-z0-9]/', '', $token);
    }

    /** @param  list<string>|null  $header */
    private function stream(string $name, callable $rows, ?array $header = null): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $header) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // so Excel reads accents correctly
            fputcsv($out, $header ?? UserCsv::header(), ',', '"', '');
            $rows($out);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
