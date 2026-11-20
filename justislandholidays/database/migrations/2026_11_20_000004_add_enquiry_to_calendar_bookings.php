<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookings page (agreed 20 Nov 2026): a stay booked with Confirm Booking remembers its enquiry,
 * so the owner's Bookings page can show the guest's name and the enquiry reference. The guest's
 * details stay in the enquiry only; when an enquiry is deleted (retention), the stay stays
 * booked and simply loses the link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('enquiry_id')->nullable()->after('feed_id');
            $table->foreign('enquiry_id', 'fk_cal_enquiry')->references('id')->on('enquiries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('calendar_bookings', function (Blueprint $table) {
            $table->dropForeign('fk_cal_enquiry');
            $table->dropColumn('enquiry_id');
        });
    }
};
