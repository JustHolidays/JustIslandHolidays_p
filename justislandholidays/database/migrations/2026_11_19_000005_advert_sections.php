<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adverts move from four fixed columns to sections (19 Nov 2026): the Menu Advert Section
 * (island and resort pages), the Sidebar Section (property pages) or both. Each section
 * is an ad slot. Adverts in the old column slots move to the Menu Advert Section, and
 * the old slots are removed. Data only; no table changes.
 */
return new class extends Migration
{
    private const SECTIONS = [
        'menu_section' => 'Menu Advert Section: island and resort pages, 4 at a time',
        'sidebar_section' => 'Sidebar Section: property pages, below Check availability',
        'both_sections' => 'Both the Menu Advert Section and the Sidebar Section',
    ];

    private const OLD = ['island_intro_1', 'island_intro_2', 'island_intro_3', 'island_intro_4'];

    public function up(): void
    {
        foreach (self::SECTIONS as $code => $description) {
            if (! DB::table('ad_slots')->where('code', $code)->exists()) {
                DB::table('ad_slots')->insert(['code' => $code, 'width' => 300, 'height' => 75, 'description' => $description]);
            }
        }

        $menu = DB::table('ad_slots')->where('code', 'menu_section')->value('id');
        $old = DB::table('ad_slots')->whereIn('code', self::OLD)->pluck('id');
        if ($old->isNotEmpty()) {
            DB::table('ads')->whereIn('slot_id', $old)->update(['slot_id' => $menu]);
            DB::table('ad_slots')->whereIn('id', $old)->delete();
        }
    }

    public function down(): void
    {
        // Not reversed: the column of each advert is not kept.
    }
};
