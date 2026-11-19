<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Our partners page (agreed 19 Nov 2026): each affiliate's home page address, and daily
 * totals of clicks on their logo there. No visitor details are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('affiliate_website_url', 500)->nullable();   // https only; affiliates only
        });

        Schema::create('partner_clicks_daily', function (Blueprint $table) {
            $table->unsignedBigInteger('affiliate_id');
            $table->date('day');
            $table->unsignedInteger('clicks')->default(0);

            $table->primary(['affiliate_id', 'day']);
            $table->foreign('affiliate_id', 'fk_pcd_affiliate')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_clicks_daily');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('affiliate_website_url');
        });
    }
};
