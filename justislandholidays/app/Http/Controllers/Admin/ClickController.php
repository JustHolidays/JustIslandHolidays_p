<?php

namespace App\Http\Controllers\Admin;

use App\Models\AffiliateCommission;
use App\Models\DeeplinkClick;
use App\Models\Listing;
use App\Models\User;
use App\Support\CsvExport;
use App\Support\Database\MySql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Click reports for the admin's islands (read only for island admins).
 * Bots are left out unless "Include bots" is on. The IP hash is never shown.
 */
class ClickController extends AdminController
{
    private const GROUPS = ['listing' => 'Listing', 'affiliate' => 'Lister', 'island' => 'Island', 'source' => 'Page the click came from'];

    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $base = $this->filtered($request, $from, $to);

        $summary = (clone $base)->selectRaw('COUNT(*) AS clicks, SUM(CASE WHEN is_unique = 1 THEN 1 ELSE 0 END) AS uniques')->first();

        $period = in_array($request->query('period'), ['day', 'week', 'month'], true) ? $request->query('period') : 'day';
        $periodExpr = $this->periodExpression($period);
        $byPeriod = (clone $base)->selectRaw("{$periodExpr} AS period, COUNT(*) AS clicks, SUM(CASE WHEN is_unique = 1 THEN 1 ELSE 0 END) AS uniques")
            ->groupBy('period')->orderBy('period')->get();

        $group = array_key_exists($request->query('group'), self::GROUPS) ? $request->query('group') : 'listing';
        $grouped = $this->grouped(clone $base, $group);

        $matched = $this->scopedCommissions()->whereNotNull('click_id')
            ->whereIn('click_id', (clone $base)->where('is_unique', true)->select('click_id'))->count();

        return view('admin.clicks.index', [
            'from' => $from, 'to' => $to, 'summary' => $summary, 'byPeriod' => $byPeriod, 'period' => $period,
            'group' => $group, 'groups' => self::GROUPS, 'grouped' => $grouped, 'matched' => $matched,
            'islands' => $this->ctx()->islands(),
            'affiliates' => User::whereIn('id', $this->ctx()->scope(Listing::query())->select('owner_id'))->orderBy('name')->get(['id', 'name']),
            'includeBots' => $request->boolean('bots'),
        ]);
    }

    /** Raw click events (13 months are kept). The IP hash is never shown. */
    public function events(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('admin.clicks.events', [
            'clicks' => $this->filtered($request, $from, $to)->with(['listing.translations'])->latest('clicked_at')->paginate(50)->withQueryString(),
            'from' => $from, 'to' => $to,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $rows = $this->filtered($request, $from, $to)->with(['listing.translations', 'affiliate'])->orderBy('clicked_at')->lazy(500);

        return CsvExport::download('clicks-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv',
            ['Time', 'Click id', 'Listing', 'Lister', 'Island id', 'Source page', 'Language', 'Device', 'Bot', 'Unique'],
            (function () use ($rows) {
                foreach ($rows as $c) {
                    yield [$c->clicked_at, $c->click_id, $c->listing?->title('en'), $c->affiliate?->name, $c->island_id, $c->source_page, $c->lang, $c->device, $c->is_bot, $c->is_unique];
                }
            })());
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
    {
        $parse = fn ($v, $default) => rescue(fn () => $v ? Carbon::parse($v) : $default, $default, false);
        $from = $parse($request->query('from'), now()->subDays(30))->startOfDay();
        $to = $parse($request->query('to'), now())->endOfDay();
        $from = $from->max(now()->subMonths(13)->startOfDay());

        return $from->gt($to) ? [$to->copy()->startOfDay(), $from->copy()->endOfDay()] : [$from, $to];
    }

    private function filtered(Request $request, Carbon $from, Carbon $to): Builder
    {
        $q = $this->ctx()->scope(DeeplinkClick::query())->whereBetween('clicked_at', [$from, $to]);

        if (! $request->boolean('bots')) {
            $q->where('is_bot', false);
        }
        if ($id = $request->integer('affiliate')) {
            $q->where('affiliate_id', $id);
        }
        if ($id = $request->integer('listing')) {
            $q->where('listing_id', $id);
        }
        if ($id = $request->integer('island')) {
            $q->where('island_id', $id);   // still inside AdminScope
        }
        foreach (['source' => 'source_page', 'lang' => 'lang', 'device' => 'device'] as $param => $column) {
            if (is_string($v = $request->query($param)) && preg_match('/^[a-z]{2,10}$/', $v)) {
                $q->where($column, $v);
            }
        }

        return $q;
    }

    private function grouped(Builder $q, string $group)
    {
        $column = ['listing' => 'listing_id', 'affiliate' => 'affiliate_id', 'island' => 'island_id', 'source' => 'source_page'][$group];

        $rows = $q->selectRaw("{$column} AS k, COUNT(*) AS clicks, SUM(CASE WHEN is_unique = 1 THEN 1 ELSE 0 END) AS uniques")
            ->groupBy('k')->orderByDesc('clicks')->limit(100)->get();

        $labels = match ($group) {
            'listing' => Listing::withTrashed()->with('translations')->whereIn('id', $rows->pluck('k'))->get()->mapWithKeys(fn ($l) => [$l->id => $l->title('en')]),
            'affiliate' => User::whereIn('id', $rows->pluck('k'))->pluck('name', 'id'),
            'island' => $this->ctx()->islands()->mapWithKeys(fn ($i) => [$i->id => $i->translate('name', 'en')]),
            default => collect(),
        };

        return $rows->map(fn ($r) => ['label' => $labels[$r->k] ?? ucfirst((string) $r->k), 'clicks' => (int) $r->clicks, 'uniques' => (int) $r->uniques]);
    }

    private function scopedCommissions(): Builder
    {
        return $this->ctx()->scopeVia(AffiliateCommission::query(), 'listing');
    }

    private function periodExpression(string $period): string
    {
        if (MySql::isMySql()) {
            return match ($period) {
                'week' => "DATE_FORMAT(clicked_at, '%x-W%v')",
                'month' => "DATE_FORMAT(clicked_at, '%Y-%m')",
                default => 'DATE(clicked_at)',
            };
        }

        return match ($period) {
            'week' => "strftime('%Y-W%W', clicked_at)",
            'month' => "strftime('%Y-%m', clicked_at)",
            default => 'date(clicked_at)',
        };
    }
}
