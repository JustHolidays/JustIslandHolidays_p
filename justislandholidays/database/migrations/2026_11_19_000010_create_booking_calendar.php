<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Booking calendar (agreed 19 Nov 2026), for properties on paid plans:
 * - calendar_feeds: iCal addresses (Airbnb, Booking.com, Google Calendar and others) per property.
 * - calendar_bookings: booked stays as check-in and check-out dates (check-out is the morning
 *   the guest leaves, not a booked night). feed_id is null for dates the owner marked by hand.
 *   Only dates are kept, never guest names or other details from the feeds.
 * Paid plans get the "Booking calendar" feature switched on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_feeds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->string('name', 60);                       // e.g. Airbnb, Google Calendar
            $table->string('url', 1000);                      // https only; a private address, never shown publicly
            $table->timestamp('last_synced_at')->nullable();
            $table->enum('last_status', ['never', 'ok', 'failed'])->default('never');
            $table->string('last_error', 255)->nullable();
            $table->timestamps();

            $table->index('listing_id', 'idx_feeds_listing');
            $table->foreign('listing_id', 'fk_feeds_listing')->references('id')->on('listings')->cascadeOnDelete();
        });

        Schema::create('calendar_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->unsignedBigInteger('feed_id')->nullable();   // null: marked by the owner
            $table->date('check_in');
            $table->date('check_out');                          // the morning they leave
            $table->timestamp('created_at')->useCurrent();

            $table->index(['listing_id', 'check_in'], 'idx_cal_listing');
            $table->foreign('listing_id', 'fk_cal_listing')->references('id')->on('listings')->cascadeOnDelete();
            $table->foreign('feed_id', 'fk_cal_feed')->references('id')->on('calendar_feeds')->cascadeOnDelete();
        });
        \App\Support\Database\MySql::statement('ALTER TABLE calendar_bookings ADD CONSTRAINT chk_cal_dates CHECK (check_out > check_in)');

        DB::table('plans')->whereIn('code', ['standard', 'featured', 'agent_plus'])->update(['has_booking_calendar' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_bookings');
        Schema::dropIfExists('calendar_feeds');
    }
};
