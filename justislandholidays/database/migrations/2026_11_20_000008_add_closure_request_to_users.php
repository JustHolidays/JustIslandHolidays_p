<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account closure requests (agreed 20 Nov 2026): owners, agents and visitors press "Close my
 * account"; the request waits on the admin Account closures page until a super admin closes
 * the account (deleted, personal details purged 30 days later) or declines it with a reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('closure_requested_at')->nullable()->after('deactivated_at');
            $table->string('closure_reason', 500)->nullable()->after('closure_requested_at');
            $table->index('closure_requested_at', 'idx_users_closure');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_closure');
            $table->dropColumn(['closure_requested_at', 'closure_reason']);
        });
    }
};
