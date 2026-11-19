<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plan names (19 Nov 2026): Free Plan, Owners Plan, Featured Plan. Codes are unchanged.
 * Only renames a plan still called by its previous name, so a name a super admin has
 * since chosen is never overwritten. Data only.
 */
return new class extends Migration
{
    private const RENAMES = [
        'free' => ['Free', 'Free Plan'],
        'standard' => ['Owners', 'Owners Plan'],
        'featured' => ['Featured Property', 'Featured Plan'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $code => [$from, $to]) {
            DB::table('plans')->where('code', $code)->where('name', $from)->update(['name' => $to]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $code => [$from, $to]) {
            DB::table('plans')->where('code', $code)->where('name', $to)->update(['name' => $from]);
        }
    }
};
