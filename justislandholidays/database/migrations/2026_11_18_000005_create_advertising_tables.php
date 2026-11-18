<?php

use App\Support\Database\MySql;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advertising group: advertisers, ad_slots, ads, ad_stats_daily, affiliate_links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('contact_email', 190)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ad_slots', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 50)->unique();
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('description', 255)->nullable();
        });

        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('advertiser_id');
            $table->unsignedSmallInteger('slot_id');
            $table->unsignedSmallInteger('island_id')->nullable();   // NULL = every island
            $table->string('image_path', 255);
            $table->string('alt', 190);
            $table->string('target_url', 500);
            $table->enum('status', ['draft', 'active', 'paused', 'ended'])->default('draft');
            $table->unsignedSmallInteger('weight')->default(1);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'slot_id', 'island_id', 'starts_at', 'ends_at'], 'idx_ads_active');
            $table->foreign('advertiser_id', 'fk_ads_advertiser')->references('id')->on('advertisers');
            $table->foreign('slot_id', 'fk_ads_slot')->references('id')->on('ad_slots');
            $table->foreign('island_id', 'fk_ads_island')->references('id')->on('islands');
        });
        MySql::statement('ALTER TABLE ads ADD CONSTRAINT chk_ad_dates CHECK (ends_at > starts_at)');
        MySql::statement('ALTER TABLE ads ADD CONSTRAINT chk_ad_weight CHECK (weight > 0)');

        Schema::create('ad_stats_daily', function (Blueprint $table) {
            $table->unsignedBigInteger('ad_id');
            $table->date('stat_date');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);

            $table->primary(['ad_id', 'stat_date']);
            $table->foreign('ad_id', 'fk_stats_ad')->references('id')->on('ads')->cascadeOnDelete();
        });

        Schema::create('affiliate_links', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('partner', 80);
            $table->enum('category', ['flights', 'car_hire', 'hotels', 'transfers', 'insurance']);
            $table->unsignedSmallInteger('island_id')->nullable();
            $table->string('url_template', 500);                     // may use {island} and {lang}
            $table->boolean('is_active')->default(true);

            $table->foreign('island_id', 'fk_aff_island')->references('id')->on('islands');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_links');
        Schema::dropIfExists('ad_stats_daily');
        Schema::dropIfExists('ads');
        Schema::dropIfExists('ad_slots');
        Schema::dropIfExists('advertisers');
    }
};
