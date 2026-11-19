<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Electric car charging" is no longer a property facility (19 Nov 2026). Removed from
 * every listing that had it, then from the list. Data only; no table changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('amenities')->where('code', 'ev_charging')->value('id');
        if ($id === null) {
            return;
        }

        DB::table('listing_amenities')->where('amenity_id', $id)->delete();
        DB::table('amenities')->where('id', $id)->delete();
    }

    public function down(): void
    {
        // Not restored: listings that had it would not get it back anyway.
    }
};
