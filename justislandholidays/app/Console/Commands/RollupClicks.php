<?php

namespace App\Console\Commands;

use App\Support\Database\MySql;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds deeplink_clicks_daily from the raw click events for a range of days.
 * The daily totals are kept after the raw events are deleted (13 months).
 */
class RollupClicks extends Command
{
    protected $signature = 'clicks:rollup {--days= : How many recent days to rebuild (default from config)} {--from= : First day, Y-m-d} {--to= : Last day, Y-m-d}';

    protected $description = 'Rebuild the daily Check availability click totals';

    public function handle(): int
    {
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->startOfDay() : now()->startOfDay();
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : $to->copy()->subDays(max(1, (int) ($this->option('days') ?? config('site.clicks.rollup_days'))) - 1);

        $rows = self::rebuild($from, $to);
        $this->info("Click totals rebuilt for {$from->toDateString()} to {$to->toDateString()}: {$rows} listing-days.");

        return self::SUCCESS;
    }

    /** Rebuilds the totals for every day from $from to $to inclusive. Returns the rows written. */
    public static function rebuild(Carbon $from, Carbon $to): int
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $day = MySql::isMySql() ? 'DATE(clicked_at)' : 'date(clicked_at)';

        $totals = DB::table('deeplink_clicks')
            ->whereBetween('clicked_at', [$start, $end])
            ->groupBy('listing_id', DB::raw($day))
            ->selectRaw("listing_id, {$day} AS day,
                SUM(CASE WHEN is_bot = 0 THEN 1 ELSE 0 END) AS clicks,
                SUM(CASE WHEN is_bot = 0 AND is_unique = 1 THEN 1 ELSE 0 END) AS unique_clicks,
                SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) AS bot_clicks")
            ->get()
            ->map(fn ($r) => [
                'listing_id' => $r->listing_id,
                'day' => substr((string) $r->day, 0, 10),
                'clicks' => (int) $r->clicks,
                'unique_clicks' => (int) $r->unique_clicks,
                'bot_clicks' => (int) $r->bot_clicks,
            ]);

        DB::transaction(function () use ($start, $end, $totals) {
            // Days in the range that no longer have events (all deleted) keep their totals,
            // so only replace rows for days that still have raw events.
            $days = $totals->pluck('day')->unique()->values();
            if ($days->isEmpty()) {
                return;
            }
            DB::table('deeplink_clicks_daily')->whereIn('day', $days)->delete();
            foreach ($totals->chunk(500) as $chunk) {
                DB::table('deeplink_clicks_daily')->insert($chunk->values()->all());
            }
        });

        return $totals->count();
    }
}
