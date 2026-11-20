<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link type for owner and agent links (agreed 20 Nov 2026), paid plans only:
 * booking  - a booking page: "Check availability" (the only type for affiliates)
 * website  - their own website: "Visit Our Website"
 * facebook - their Facebook page: "Visit Our Facebook"
 * Existing links stay booking pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('link_type', 10)->default('booking')->after('deeplink_subid_param');
        });
        \App\Support\Database\MySql::statement("ALTER TABLE listings ADD CONSTRAINT chk_link_type CHECK (link_type IN ('booking', 'website', 'facebook'))");
    }

    public function down(): void
    {
        \App\Support\Database\MySql::statement('ALTER TABLE listings DROP CHECK chk_link_type');
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('link_type');
        });
    }
};
