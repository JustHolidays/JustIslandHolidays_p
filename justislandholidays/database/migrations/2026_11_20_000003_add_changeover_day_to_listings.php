<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Changeover day (agreed 20 Nov 2026), set in the editor's Calendar step (paid plans):
 * null = not chosen yet, 0 = guests can arrive any day (every rate period then needs a
 * nightly rate), 1 to 7 = one fixed changeover day, Monday (1) to Sunday (7), shown above
 * the calendar on the property page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->unsignedTinyInteger('changeover_day')->nullable()->after('link_type');
        });
        \App\Support\Database\MySql::statement('ALTER TABLE listings ADD CONSTRAINT chk_changeover_day CHECK (changeover_day IS NULL OR changeover_day <= 7)');
    }

    public function down(): void
    {
        \App\Support\Database\MySql::statement('ALTER TABLE listings DROP CHECK chk_changeover_day');
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('changeover_day');
        });
    }
};
