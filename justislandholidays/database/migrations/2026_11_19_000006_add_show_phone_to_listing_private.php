<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owners choose whether their phone number is public on paid plans (agreed 19 Nov 2026).
 * Off for everyone to start, existing listings included: a phone is only shown once the
 * owner says so. The number itself is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_private', function (Blueprint $table) {
            $table->boolean('show_phone')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('listing_private', function (Blueprint $table) {
            $table->dropColumn('show_phone');
        });
    }
};
