<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliate logo (agreed 19 Nov 2026): shown as a 100-pixel banner above Check availability
 * on the affiliate's property pages. Used only by affiliate accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('affiliate_logo_path', 255)->nullable();
            $table->string('affiliate_logo_alt', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['affiliate_logo_path', 'affiliate_logo_alt']);
        });
    }
};
