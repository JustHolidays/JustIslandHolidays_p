<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plans and photos (agreed 19 Nov 2026):
 * - plans: header image for the plan card, photo limit, a "booking calendar" feature line,
 *   and Featured Property prices for 3, 6, 9 and 12 months (a one-off payment each).
 * - Standard is renamed Owners and Featured is renamed Featured Property (names only).
 * - listing_photos: extra photos after the main one (listings.image_path stays the main
 *   photo, so every existing listing keeps its photo as photo 1).
 * - featured_purchases: one-off Featured Property payments; subscriptions.billing_interval
 *   gains 'one_off' for the period they buy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('header_image_path', 255)->nullable();
            $table->string('header_image_alt', 160)->nullable();
            $table->unsignedTinyInteger('max_photos')->default(1);
            $table->boolean('has_booking_calendar')->default(false);
            $table->unsignedInteger('price_3m_cents')->nullable();
            $table->unsignedInteger('price_6m_cents')->nullable();
            $table->unsignedInteger('price_9m_cents')->nullable();
            $table->unsignedInteger('price_12m_cents')->nullable();
        });

        foreach (['free' => 3, 'standard' => 12, 'featured' => 12, 'agent_plus' => 12] as $code => $photos) {
            DB::table('plans')->where('code', $code)->update(['max_photos' => $photos]);
        }
        DB::table('plans')->where('code', 'standard')->where('name', 'Standard')->update(['name' => 'Owners']);
        DB::table('plans')->where('code', 'featured')->where('name', 'Featured')->update(['name' => 'Featured Property']);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('billing_interval', ['month', 'year', 'one_off'])->change();
        });

        Schema::create('listing_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->string('image_path', 255)->nullable();      // largest WebP copy; null while it is being processed
            $table->string('alt', 160);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['listing_id', 'sort_order'], 'idx_photos_listing');
            $table->foreign('listing_id', 'fk_photos_listing')->references('id')->on('listings')->cascadeOnDelete();
        });

        Schema::create('featured_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('agency_id')->nullable();
            $table->unsignedSmallInteger('plan_id');
            $table->unsignedTinyInteger('months');                 // 3, 6, 9 or 12
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('paypal_order_id', 40)->nullable()->unique();
            $table->enum('status', ['created', 'completed', 'failed'])->default('created');
            $table->unsignedBigInteger('subscription_id')->nullable();   // the period it gave, once paid
            $table->timestamps();

            $table->foreign('user_id', 'fk_fp_user')->references('id')->on('users');
            $table->foreign('plan_id', 'fk_fp_plan')->references('id')->on('plans');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_purchases');
        Schema::dropIfExists('listing_photos');
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('billing_interval', ['month', 'year'])->change();
        });
        DB::table('plans')->where('code', 'standard')->where('name', 'Owners')->update(['name' => 'Standard']);
        DB::table('plans')->where('code', 'featured')->where('name', 'Featured Property')->update(['name' => 'Featured']);
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['header_image_path', 'header_image_alt', 'max_photos', 'has_booking_calendar', 'price_3m_cents', 'price_6m_cents', 'price_9m_cents', 'price_12m_cents']);
        });
    }
};
