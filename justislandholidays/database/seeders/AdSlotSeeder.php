<?php

namespace Database\Seeders;

use App\Ads\IslandAdverts;
use App\Models\AdSlot;
use Illuminate\Database\Seeder;

/** The advert sections: Menu Advert Section, Sidebar Section, and Both (banners 300 × 75). */
class AdSlotSeeder extends Seeder
{
    public function run(): void
    {
        foreach (IslandAdverts::SECTIONS as $section => $code) {
            AdSlot::updateOrCreate(['code' => $code], [
                'width' => IslandAdverts::WIDTH,
                'height' => IslandAdverts::HEIGHT,
                'description' => IslandAdverts::SECTION_LABELS[$section],
            ]);
        }
    }
}
