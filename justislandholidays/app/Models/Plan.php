<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Plan names, limits and prices live in the database, never in code. */
class Plan extends Model
{
    public $timestamps = false;

    /** Featured Property periods, in months, each with its own one-off price (19 Nov 2026). */
    public const FEATURED_PERIODS = [3, 6, 9, 12];

    protected $fillable = [
        'code', 'name', 'audience', 'max_listings', 'is_featured', 'search_boost', 'show_direct_contact',
        'currency', 'price_month_cents', 'price_year_cents', 'paypal_plan_id_month', 'paypal_plan_id_year', 'is_active',
        'header_image_path', 'header_image_alt', 'max_photos', 'has_booking_calendar',
        'price_3m_cents', 'price_6m_cents', 'price_9m_cents', 'price_12m_cents',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'show_direct_contact' => 'boolean',
            'is_active' => 'boolean',
            'has_booking_calendar' => 'boolean',
        ];
    }

    /** Featured Property is bought for a period with a one-off payment, not a yearly subscription. */
    public function isSoldByPeriod(): bool
    {
        return $this->is_featured;
    }

    /** @return array<int, int> months => price in cents, only the periods that have a price */
    public function periodPrices(): array
    {
        $prices = [];
        foreach (self::FEATURED_PERIODS as $months) {
            $cents = $this->{"price_{$months}m_cents"};
            if ($cents !== null) {
                $prices[$months] = (int) $cents;
            }
        }

        return $prices;
    }

    public function money(int $cents): string
    {
        $symbol = $this->currency === 'GBP' ? '£' : '€';

        return $symbol.(fmod($cents / 100, 1.0) === 0.0 ? number_format($cents / 100) : number_format($cents / 100, 2));
    }

    public function headerImageUrl(): ?string
    {
        return $this->header_image_path ? Storage::disk('public')->url($this->header_image_path) : null;
    }

    /** Photos a listing on this plan may show (at least 1). */
    public function photoLimit(): int
    {
        return max(1, (int) $this->max_photos);
    }
}
