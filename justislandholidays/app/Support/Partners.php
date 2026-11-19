<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Our partners page: every active affiliate, with their logo linking to their home page
 * through /go/partner/{id}, which counts the click per day in partner_clicks_daily.
 */
final class Partners
{
    /** @return Collection<int, User> active affiliates, by name */
    public static function active(): Collection
    {
        return User::where('role', Role::Affiliate->value)->whereNull('deactivated_at')->orderBy('name')
            ->get(['id', 'name', 'affiliate_logo_path', 'affiliate_logo_alt', 'affiliate_website_url']);
    }

    public static function hasWebsite(User $affiliate): bool
    {
        return is_string($affiliate->affiliate_website_url) && str_starts_with($affiliate->affiliate_website_url, 'https://');
    }

    public static function countClick(User $affiliate): void
    {
        $key = ['affiliate_id' => $affiliate->id, 'day' => now()->toDateString()];
        DB::table('partner_clicks_daily')->insertOrIgnore($key + ['clicks' => 0]);
        DB::table('partner_clicks_daily')->where($key)->increment('clicks');
    }

    /** @return array{days30: int, all: int} */
    public static function clicks(User $affiliate): array
    {
        $q = fn () => DB::table('partner_clicks_daily')->where('affiliate_id', $affiliate->id);

        return [
            'days30' => (int) $q()->where('day', '>=', now()->subDays(29)->toDateString())->sum('clicks'),
            'all' => (int) $q()->sum('clicks'),
        ];
    }
}
