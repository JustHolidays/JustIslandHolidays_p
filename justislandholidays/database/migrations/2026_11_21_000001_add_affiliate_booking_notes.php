<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Booking Notes (agreed 21 Nov 2026): text a super admin writes for each affiliate, shown to
 * visitors under Check availability on that affiliate's property pages. Simple Markdown, kept
 * per language ({"en": "...", "es": "...", "de": "..."}). Affiliates only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('affiliate_booking_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('affiliate_booking_notes');
        });
    }
};
