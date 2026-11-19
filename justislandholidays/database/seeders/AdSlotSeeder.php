<?php

namespace Database\Seeders;

use App\Ads\IslandAdverts;
use App\Models\AdSlot;
use Illuminate\Database\Seeder;

/** The four advert spaces below the introduction on island pages (300 × 75: banners shown 75 pixels tall). */
class AdSlotSeeder extends Seeder
{
    public function run(): void
    {
        foreach (IslandAdverts::SLOTS as $position => $code) {
            AdSlot::updateOrCreate(['code' => $code], [
                'width' => IslandAdverts::WIDTH,
                'height' => IslandAdverts::HEIGHT,
                'description' => "Island page, below the introduction, column {$position} of 4",
            ]);
        }
    }
}
