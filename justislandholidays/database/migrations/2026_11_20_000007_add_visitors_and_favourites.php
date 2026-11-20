<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitor accounts and favourites (agreed 20 Nov 2026):
 * - users.role gains "visitor": a free account for people looking for a holiday home. No plan,
 *   no listings, no admin access.
 * - favourites: the homes a signed-in person has saved with the heart. Only who and which
 *   home; removed with the account or the listing.
 */
return new class extends Migration
{
    private const ROLES = ['owner', 'agent', 'affiliate', 'island_admin', 'super_admin'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [...self::ROLES, 'visitor'])->default('owner')->change();
        });

        Schema::create('favourites', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('listing_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'listing_id']);
            $table->index('listing_id', 'idx_fav_listing');
            $table->foreign('user_id', 'fk_fav_user')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('listing_id', 'fk_fav_listing')->references('id')->on('listings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favourites');
        \Illuminate\Support\Facades\DB::table('users')->where('role', 'visitor')->delete();
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', self::ROLES)->default('owner')->change();
        });
    }
};
