<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\LegalPagesSeeder;
use Illuminate\Console\Command;

/**
 * The launch gate: what must be true before the live site goes public.
 * Automatic checks pass or fail; manual items must be confirmed by a person.
 * Exits with failure while any automatic check fails or any manual item is open.
 *
 *   php artisan launch:check
 *   php artisan launch:check --confirm=legal_review --by="Name of the reviewer"
 */
class LaunchCheck extends Command
{
    protected $signature = 'launch:check {--confirm= : Record a manual item as done} {--by= : Who confirmed it} {--note= : Optional note}';

    protected $description = 'Check the launch gate: licence checks, legal pages and review, security and live settings';

    /** Manual items a person confirms with --confirm=<key>. */
    public const MANUAL = [
        'legal_review' => 'A lawyer has reviewed the privacy notice and terms (Spanish tourism and UK/EU data protection)',
        'island_text_review' => 'Native speakers have checked the Spanish and German island summaries',
        'cdn_location' => 'The host has confirmed whether its CDN processes data outside the UK, and the privacy notice says so',
        'eu_2024_1028' => 'An adviser has confirmed what EU Regulation 2024/1028 needs from us as an online platform',
        'trademark_search' => 'A UK IPO search for "Just Island Holidays" has been done',
        'cron_and_queue' => 'The live server runs the scheduler every minute and a queue worker (or QUEUE_VIA_SCHEDULER=true)',
        'backups' => 'Daily database backups with 14 days kept are switched on, and a restore has been tested',
    ];

    public function handle(): int
    {
        if ($key = $this->option('confirm')) {
            return $this->confirmItem($key);
        }

        $rows = [];
        foreach ($this->automaticChecks() as [$label, $ok, $detail]) {
            $rows[] = [$ok ? 'PASS' : 'FAIL', $label, $detail];
        }

        $confirmed = SiteSetting::find('launch.confirmed')?->value ?? [];
        foreach (self::MANUAL as $key => $label) {
            $c = $confirmed[$key] ?? null;
            $rows[] = [$c ? 'DONE' : 'TO DO', $label, $c ? "by {$c['by']} on ".substr($c['at'], 0, 10) : "confirm with --confirm={$key} --by=\"Name\""];
        }

        $this->table(['', 'Launch gate', 'Detail'], $rows);

        $open = collect($rows)->whereIn(0, ['FAIL', 'TO DO'])->count();
        if ($open > 0) {
            $this->error("{$open} item(s) still open. The site is not ready to launch.");

            return self::FAILURE;
        }

        $this->info('All launch checks pass.');

        return self::SUCCESS;
    }

    /** @return list<array{0: string, 1: bool, 2: string}> */
    public function automaticChecks(): array
    {
        $legal = Page::whereNull('island_id')->whereIn('slug', LegalPagesSeeder::SLUGS)->with('translations')->get()->keyBy('slug');
        $missing = collect(LegalPagesSeeder::SLUGS)->reject(fn ($s) => $legal->has($s) && $legal[$s]->is_published && $legal[$s]->translations->firstWhere('lang', 'en'));
        $placeholders = $legal->filter(fn ($p) => str_contains((string) $p->translations->firstWhere('lang', 'en')?->body_md, '[TO CONFIRM'))->keys();
        $drafts = $legal->filter(fn ($p) => str_contains((string) $p->translations->firstWhere('lang', 'en')?->body_md, 'Draft for legal review'))->keys();

        $supers = User::active()->where('role', Role::SuperAdmin)->get();
        $supersVerified = $supers->filter(fn ($u) => $u->email_verified_at !== null)->count();
        $supersWithApp = $supers->filter->hasConfirmedTwoFactor()->count();
        $registerUrl = SiteSetting::find('licences.register_url')?->value['url'] ?? null;
        $paypalLive = config('services.paypal.mode') === 'live' && filled(config('services.paypal.client_id')) && filled(config('services.paypal.webhook_id'));
        $appUrl = (string) config('app.url');
        $canonical = (string) config('site.canonical_host');

        return [
            // Licence verification working
            ['Licence register address is set', (bool) $registerUrl, $registerUrl ?: 'Set it on the Licence checks page'],
            // Every admin sign-in needs a second step (emailed code or authenticator app), so a verified email is enough.
            ['At least two active super admins with a verified email', $supersVerified >= 2, "{$supersVerified} of {$supers->count()} verified; {$supersWithApp} use an authenticator app, the rest get emailed codes"],

            // Legal pages
            ['Legal pages exist and are published', $missing->isEmpty(), $missing->isEmpty() ? implode(', ', LegalPagesSeeder::SLUGS) : 'Missing or unpublished: '.$missing->implode(', ')],
            ['No "[TO CONFIRM" placeholders left in legal pages', $placeholders->isEmpty(), $placeholders->isEmpty() ? 'None' : 'Still in: '.$placeholders->implode(', ')],
            ['Draft notices removed from legal pages', $drafts->isEmpty(), $drafts->isEmpty() ? 'None' : 'Still in: '.$drafts->implode(', ')],
            ['Contact email is set', filled(config('site.contact_email')), config('site.contact_email') ?: 'Set SITE_CONTACT_EMAIL'],

            // Live settings
            ['Running in production mode with debug off', app()->isProduction() && ! config('app.debug'), 'APP_ENV='.app()->environment().', APP_DEBUG='.(config('app.debug') ? 'true' : 'false')],
            ['Site address uses https', str_starts_with($appUrl, 'https://'), $appUrl],
            ['Main domain set (the other redirects to it)', $canonical === 'justislandholidays.co.uk', $canonical ?: 'Set SITE_CANONICAL_HOST=justislandholidays.co.uk'],
            ['Search engines allowed (pre-launch block switched off)', ! config('site.block_indexing'), config('site.block_indexing') ? 'Set SITE_BLOCK_INDEXING=false at launch' : 'Allowed'],
            ['Email is sent through a real provider', ! in_array(config('mail.default'), ['log', 'array'], true), 'MAIL_MAILER='.config('mail.default')],
            ['Database is MySQL', config('database.default') === 'mysql', 'DB_CONNECTION='.config('database.default')],
            ['PayPal is in live mode with a webhook', $paypalLive, 'PAYPAL_MODE='.config('services.paypal.mode')],
            // Read through config (env() is empty once the config is cached in production).
            ['Separate secret for visitor address hashing', $this->ownSecret(config('site.ip_hash_salt')), $this->ownSecret(config('site.ip_hash_salt')) ? 'Set' : 'Set IP_HASH_SALT to a long random value'],
            ['Separate secret for the spam check', $this->ownSecret(config('site.altcha.hmac_key')), $this->ownSecret(config('site.altcha.hmac_key')) ? 'Set' : 'Set ALTCHA_HMAC_KEY to a long random value'],
        ];
    }

    /** A secret of its own: set, long enough, and not just the app key reused. */
    private function ownSecret(mixed $value): bool
    {
        return is_string($value) && strlen($value) >= 32 && $value !== config('app.key');
    }

    private function confirmItem(string $key): int
    {
        if (! array_key_exists($key, self::MANUAL)) {
            $this->error('Unknown item. Use one of: '.implode(', ', array_keys(self::MANUAL)));

            return self::FAILURE;
        }
        $by = trim((string) $this->option('by'));
        if ($by === '') {
            $this->error('Say who confirmed it with --by="Name".');

            return self::FAILURE;
        }

        $row = SiteSetting::find('launch.confirmed') ?? new SiteSetting(['setting_key' => 'launch.confirmed', 'value' => []]);
        $value = $row->value ?? [];
        $value[$key] = ['by' => $by, 'at' => now()->toIso8601String(), 'note' => $this->option('note')];
        $row->value = $value;
        $row->save();

        \App\Support\Audit::record(null, 'launch.confirm', 'site_setting', 'launch.confirmed', ['item' => $key, 'by' => $by, 'note' => $this->option('note')]);
        $this->info('Recorded: '.self::MANUAL[$key].", confirmed by {$by}.");

        return self::SUCCESS;
    }
}
