<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rates table (agreed 20 Nov 2026), for owner and agent properties on paid plans:
 * one row per period, from start_date to end_date (both included), with a nightly,
 * weekly and 14-night rate in the listing's currency. At least one price per row; the rest
 * may be empty. While a property has a weekly rate still to come, its From price is the
 * lowest of them (App\Listings\Rates).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->date('start_date');
            $table->date('end_date');                              // the last day of the period
            $table->unsignedInteger('nightly_cents')->nullable();
            $table->unsignedInteger('weekly_cents')->nullable();
            $table->unsignedInteger('fortnight_cents')->nullable(); // 14 nights

            $table->index(['listing_id', 'start_date'], 'idx_rates_listing');
            $table->foreign('listing_id', 'fk_rates_listing')->references('id')->on('listings')->cascadeOnDelete();
        });
        \App\Support\Database\MySql::statement('ALTER TABLE listing_rates ADD CONSTRAINT chk_rates_dates CHECK (end_date >= start_date)');
        \App\Support\Database\MySql::statement('ALTER TABLE listing_rates ADD CONSTRAINT chk_rates_price CHECK (nightly_cents IS NOT NULL OR weekly_cents IS NOT NULL OR fortnight_cents IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_rates');
    }
};
