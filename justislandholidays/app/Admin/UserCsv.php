<?php

namespace App\Admin;

use App\Billing\AccountPlan;
use App\Enums\Role;
use App\Models\Agency;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Owner and agent CSV import and export for super admins (21 Nov 2026).
 *
 * Rows are matched by email. A new email adds an owner or agent account on the Free Plan with a
 * random password nobody knows; no email is sent (invitations go out later from each person's
 * page). An email that already belongs to an owner or agent updates only the changed name,
 * phone and agency. Email, role and plan never change through the file, and passwords are never
 * in it. Admin, affiliate and visitor accounts cannot be added or changed. Every import is
 * previewed first and audited.
 */
final class UserCsv
{
    public const MAX_ROWS = 1000;

    /** Columns read on import. */
    public const COLUMNS = [
        'email' => 'Email address. Matches an existing owner or agent; a new address adds an account. Cannot be changed through the file.',
        'name' => 'Full name, up to 160 characters.',
        'role' => 'owner or agent. Used for new accounts only: change an existing person\'s role on their page.',
        'phone' => 'Phone number, or empty.',
        'agency' => 'Agents only: the agency\'s name exactly as it is in the admin, or empty.',
    ];

    /** Columns only in exports, for reference; ignored on import. */
    public const EXPORT_ONLY = [
        'status' => 'active, deactivated or closure requested.',
        'plan' => 'Current plan.',
        'listings' => 'Number of properties they list.',
        'email_confirmed' => 'yes or no.',
        'last_sign_in' => 'Date and time of their last sign-in, or empty.',
        'joined' => 'Date the account was created.',
    ];

    public function __construct(private AccountPlan $plans)
    {
    }

    /** @return list<string> */
    public static function header(): array
    {
        return [...array_keys(self::COLUMNS), ...array_keys(self::EXPORT_ONLY)];
    }

    // --- Export ---------------------------------------------------------------------------

    public function exportQuery(?string $role = null, ?string $status = null)
    {
        return User::query()
            ->whereIn('role', $role ? [$role] : [Role::Owner->value, Role::Agent->value])
            ->when($status === 'active', fn ($q) => $q->whereNull('deactivated_at'))
            ->when($status === 'deactivated', fn ($q) => $q->whereNotNull('deactivated_at'))
            ->with('agency')
            ->withCount('listings')
            ->orderBy('role')->orderBy('name');
    }

    /** @return list<string> */
    public function row(User $user): array
    {
        $status = $user->isDeactivated() ? 'deactivated' : ($user->closure_requested_at ? 'closure requested' : 'active');

        return array_map([ListingCsv::class, 'safeCell'], [
            $user->email,
            $user->name,
            $user->role->value,
            $user->phone,
            $user->role === Role::Agent ? $user->agency?->name : '',
            $status,
            $this->plans->planFor($this->plans->accountFor($user))->name,
            $user->listings_count,
            $user->email_verified_at ? 'yes' : 'no',
            $user->last_login_at?->timezone('Europe/London')->format('Y-m-d H:i'),
            $user->created_at?->format('Y-m-d'),
        ]);
    }

    // --- Import ---------------------------------------------------------------------------

    /**
     * Reads a CSV file into rows keyed by column name (export-only columns are dropped).
     *
     * @return array{rows: list<array{line: int, values: array<string, string>}>, error: ?string, ignored: list<string>}
     */
    public function read(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);   // Excel's byte order mark
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $firstLine = strtok($raw, "\n");
        $delimiter = substr_count((string) $firstLine, ';') > substr_count((string) $firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $raw);
        rewind($handle);

        $header = fgetcsv($handle, null, $delimiter, '"', '');
        if (! $header) {
            return ['rows' => [], 'error' => 'The file is empty.', 'ignored' => []];
        }
        $header = array_map(fn ($h) => Str::snake(trim((string) $h)), $header);
        $missing = array_diff(['email', 'name'], $header);
        if ($missing !== []) {
            fclose($handle);

            return ['rows' => [], 'error' => 'The first row must be the column names from the template (missing: '.implode(', ', $missing).').', 'ignored' => []];
        }
        $ignored = array_values(array_diff(array_filter($header), self::header()));

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $line++;
            if ($cells === [null] || implode('', array_map('trim', array_map('strval', $cells))) === '') {
                continue;   // blank line
            }
            $values = [];
            foreach ($header as $i => $column) {
                if (isset(self::COLUMNS[$column])) {
                    $values[$column] = self::unsafeCell(trim((string) ($cells[$i] ?? '')));
                }
            }
            $rows[] = ['line' => $line, 'values' => $values];
            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);

                return ['rows' => [], 'error' => 'Up to '.self::MAX_ROWS.' rows at a time. Split the file and import each part.', 'ignored' => $ignored];
            }
        }
        fclose($handle);

        return ['rows' => $rows, 'error' => $rows === [] ? 'There are no rows under the column names.' : null, 'ignored' => $ignored];
    }

    /** Undoes ListingCsv::safeCell(): a leading ' before = + - @ is dropped again. */
    private static function unsafeCell(string $text): string
    {
        return preg_match("/^'[=+\\-@]/", $text) ? substr($text, 1) : $text;
    }

    /**
     * Checks one row and works out what it would do.
     *
     * @param  array<string, string>  $v
     * @param  array<string, int>  $seen  emails already used by earlier rows (line numbers)
     * @return array{line: int, email: string, name: string, action: string, changes: array<string, array{0: mixed, 1: mixed}>, errors: list<string>, notes: list<string>, data: array<string, mixed>}
     */
    public function check(int $line, array $v, array &$seen = []): array
    {
        $email = Str::lower(trim($v['email'] ?? ''));
        $result = ['line' => $line, 'email' => $email, 'name' => trim($v['name'] ?? ''), 'action' => 'add', 'changes' => [], 'errors' => [], 'notes' => [], 'data' => []];
        $err = function (string $message) use (&$result) {
            $result['errors'][] = $message;
        };

        if ($email === '' || Validator::make(['e' => $email], ['e' => ['email', 'max:190']])->fails()) {
            $err($email === '' ? 'The email is missing.' : 'The email "'.$email.'" is not a valid address.');

            return $result;
        }
        if (isset($seen[$email])) {
            $err('This email is already on line '.$seen[$email].' of the file.');

            return $result;
        }
        $seen[$email] = $line;

        $user = User::withTrashed()->where('email', $email)->with('agency')->first();
        if ($user && ($user->trashed() || ! in_array($user->role, [Role::Owner, Role::Agent], true))) {
            $err($user->trashed()
                ? 'This email belongs to a closed account.'
                : 'This email is used by another kind of account ('.$user->role->label().'), which cannot be changed here.');

            return $result;
        }

        $name = $result['name'];
        if ($name === '') {
            $err('The name is missing.');
        } elseif (mb_strlen($name) > 160) {
            $err('The name is longer than 160 characters.');
        }

        $phone = trim($v['phone'] ?? '');
        if (mb_strlen($phone) > 40) {
            $err('The phone number is longer than 40 characters.');
        }

        $roleText = Str::lower(trim($v['role'] ?? ''));
        if ($user) {
            $role = $user->role;
            if ($roleText !== '' && $roleText !== $role->value) {
                $result['notes'][] = 'The role stays '.$role->value.': change it on their page.';
            }
        } else {
            $role = Role::tryFrom($roleText);
            if (! in_array($role, [Role::Owner, Role::Agent], true)) {
                $err($roleText === '' ? 'The role is missing: owner or agent.' : 'The role "'.$roleText.'" must be owner or agent.');
                $role = null;
            }
        }

        $agencyName = trim($v['agency'] ?? '');
        $agency = null;
        if ($agencyName !== '') {
            if ($role === Role::Owner) {
                $result['notes'][] = 'The agency is left out: owners do not belong to an agency.';
            } else {
                $agency = Agency::whereRaw('LOWER(name) = ?', [Str::lower($agencyName)])->first();
                if (! $agency) {
                    $err('There is no agency called "'.$agencyName.'".');
                }
            }
        }

        if ($result['errors'] !== []) {
            return $result;
        }

        $data = ['email' => $email, 'name' => $name, 'phone' => $phone !== '' ? $phone : null, 'role' => $role->value, 'agency_id' => $role === Role::Agent ? $agency?->id : null];
        $result['data'] = $data;

        if (! $user) {
            $result['notes'][] = 'New '.$role->value.' on the Free Plan. No email is sent: use Send invitation on their page when they are ready.';

            return $result;
        }

        $result['action'] = 'update';
        $result['data']['id'] = $user->id;
        if ($user->name !== $data['name']) {
            $result['changes']['name'] = [$user->name, $data['name']];
        }
        if (($user->phone ?: null) !== $data['phone']) {
            $result['changes']['phone'] = [$user->phone, $data['phone']];
        }
        if ($user->role === Role::Agent && $user->agency_id !== $data['agency_id']) {
            $result['changes']['agency'] = [$user->agency?->name, $agency?->name];
        }
        if ($result['changes'] === []) {
            $result['action'] = 'unchanged';
        }
        if ($user->isDeactivated()) {
            $result['notes'][] = 'This account is deactivated and stays so.';
        }

        return $result;
    }

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return Collection<int, array>
     */
    public function checkAll(array $rows): Collection
    {
        $seen = [];

        return collect($rows)->map(function ($r) use (&$seen) {   // not fn(): $seen must carry over
            return $this->check($r['line'], $r['values'], $seen);
        });
    }

    /**
     * Saves the rows that passed their checks.
     *
     * @return array{added: int, updated: int, skipped: int}
     */
    public function apply(Collection $checked, User $admin): array
    {
        $counts = ['added' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($checked as $row) {
            if ($row['errors'] !== [] || $row['action'] === 'unchanged') {
                $counts['skipped']++;

                continue;
            }
            $d = $row['data'];

            DB::transaction(function () use ($row, $d, $admin, &$counts) {
                if ($row['action'] === 'add') {
                    $user = new User(['name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'], 'password' => Str::password(40)]);
                    $user->role = Role::from($d['role']);
                    $user->agency_id = $d['agency_id'];
                    $user->save();
                    Audit::record($admin, 'user.csv_create', $user, null, ['role' => $d['role'], 'line' => $row['line']]);
                    $counts['added']++;

                    return;
                }

                $user = User::find($d['id']);
                $before = $user->only(['name', 'phone', 'agency_id']);
                $user->forceFill([
                    'name' => $d['name'],
                    'phone' => $d['phone'],
                    'agency_id' => $user->role === Role::Agent ? $d['agency_id'] : null,
                ])->save();
                Audit::record($admin, 'user.csv_update', $user, null, ['before' => $before, 'after' => $user->only(['name', 'phone', 'agency_id']), 'line' => $row['line']]);
                $counts['updated']++;
            });
        }

        return $counts;
    }
}
