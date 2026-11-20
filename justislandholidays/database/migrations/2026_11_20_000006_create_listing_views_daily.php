<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Property page views (agreed 20 Nov 2026): one count per property per day, nothing about the
 * visitor. Known bots are left out, and a visitor counts once per property in 30 minutes
 * (a salted hash kept only in the cache, App\Listings\PageViews). Shown to owners and agents
 * on Views and clicks, and to admins on the property's admin page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_views_daily', function (Blueprint $table) {
            $table->unsignedBigInteger('listing_id');
            $table->date('day');
            $table->unsignedInteger('views')->default(0);

            $table->primary(['listing_id', 'day']);
            $table->foreign('listing_id', 'fk_lvd_listing')->references('id')->on('listings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_views_daily');
    }
};
