<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest reference (agreed 20 Nov 2026): a short note the owner or agent adds to a stay they
 * marked themselves, for example "Smith family" or "Booking.com 4471", shown in the Guest
 * column of their Bookings page. It may be a name, so data:retention clears it 24 months
 * after the stay ends; the stay itself stays booked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_bookings', function (Blueprint $table) {
            $table->string('guest_ref', 100)->nullable()->after('enquiry_id');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_bookings', function (Blueprint $table) {
            $table->dropColumn('guest_ref');
        });
    }
};
