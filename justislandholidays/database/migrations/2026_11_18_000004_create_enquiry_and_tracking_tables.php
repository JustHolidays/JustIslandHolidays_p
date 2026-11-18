<?php

use App\Support\Database\MySql;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enquiries and tracking group: enquiries, deeplink_clicks, deeplink_clicks_daily, affiliate_commissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->unsignedBigInteger('owner_id');                  // recipient at the time of sending
            $table->string('guest_name', 120);
            $table->string('guest_email', 190);
            $table->string('guest_phone', 40)->nullable();
            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->unsignedTinyInteger('adults')->nullable();
            $table->unsignedTinyInteger('children')->nullable();
            $table->text('message');
            $table->enum('lang', ['en', 'es', 'de'])->default('en');
            $table->enum('status', ['new', 'replied', 'closed', 'spam'])->default('new');
            $table->timestamp('consent_given_at');
            $table->char('ip_hash', 64)->nullable();                 // salted hash, never the raw IP
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['owner_id', 'status', 'created_at'], 'idx_enq_inbox');
            $table->index(['listing_id', 'created_at'], 'idx_enq_listing');
            $table->foreign('listing_id', 'fk_enq_listing')->references('id')->on('listings');
            $table->foreign('owner_id', 'fk_enq_owner')->references('id')->on('users');
        });
        MySql::statement('ALTER TABLE enquiries ADD CONSTRAINT chk_enq_dates CHECK (check_in IS NULL OR check_out IS NULL OR check_out > check_in)');
        MySql::statement('ALTER TABLE enquiries ADD CONSTRAINT chk_enq_adults CHECK (adults IS NULL OR adults >= 1)');
        MySql::statement('ALTER TABLE enquiries ADD CONSTRAINT chk_enq_msg CHECK (CHAR_LENGTH(message) <= 4000)');

        // One row per Check availability click. Raw events kept for 13 months.
        Schema::create('deeplink_clicks', function (Blueprint $table) {
            $table->id();
            $table->char('click_id', 26)->unique();                  // ULID added to the outgoing link
            $table->unsignedBigInteger('listing_id');
            $table->unsignedBigInteger('affiliate_id');              // the user who holds the listing
            $table->unsignedSmallInteger('island_id');
            $table->timestamp('clicked_at')->useCurrent();
            $table->enum('lang', ['en', 'es', 'de']);
            $table->enum('source_page', ['home', 'island', 'area', 'search', 'listing', 'other']);
            $table->enum('device', ['desktop', 'mobile', 'tablet', 'unknown'])->default('unknown');
            $table->char('ip_hash', 64);                             // salted daily hash, never the raw IP
            $table->boolean('is_bot')->default(false);
            $table->boolean('is_unique')->default(true);
            $table->string('target_url', 2048);

            $table->index(['listing_id', 'clicked_at'], 'idx_clicks_listing');
            $table->index(['affiliate_id', 'clicked_at'], 'idx_clicks_affiliate');
            $table->index(['island_id', 'clicked_at'], 'idx_clicks_island');
            $table->index(['ip_hash', 'listing_id', 'clicked_at'], 'idx_clicks_dedupe');
            $table->foreign('listing_id', 'fk_clicks_listing')->references('id')->on('listings');
            $table->foreign('affiliate_id', 'fk_clicks_affiliate')->references('id')->on('users');
        });

        // Daily totals, kept after raw events are deleted
        Schema::create('deeplink_clicks_daily', function (Blueprint $table) {
            $table->unsignedBigInteger('listing_id');
            $table->date('day');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('unique_clicks')->default(0);
            $table->unsignedInteger('bot_clicks')->default(0);

            $table->primary(['listing_id', 'day']);
            $table->foreign('listing_id', 'fk_dc_listing')->references('id')->on('listings')->cascadeOnDelete();
        });

        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id')->nullable();
            $table->unsignedBigInteger('affiliate_id');
            $table->char('click_id', 26)->nullable();                // no FK: old clicks are deleted
            $table->string('programme_ref', 80);
            $table->unsignedInteger('booking_value_cents')->nullable();
            $table->unsignedInteger('commission_cents');
            $table->char('currency', 3)->default('EUR');
            $table->enum('status', ['pending', 'approved', 'paid', 'rejected'])->default('pending');
            $table->date('booked_at')->nullable();
            $table->timestamp('reported_at')->useCurrent();
            $table->unsignedBigInteger('imported_by');

            $table->unique(['affiliate_id', 'programme_ref'], 'uq_commission_ref');
            $table->index(['listing_id', 'status'], 'idx_comm_listing');
            $table->index('click_id', 'idx_comm_click');
            $table->foreign('listing_id', 'fk_comm_listing')->references('id')->on('listings');
            $table->foreign('affiliate_id', 'fk_comm_affiliate')->references('id')->on('users');
            $table->foreign('imported_by', 'fk_comm_importer')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('deeplink_clicks_daily');
        Schema::dropIfExists('deeplink_clicks');
        Schema::dropIfExists('enquiries');
    }
};
