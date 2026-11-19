<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A full-width header image for each island page (agreed 19 Nov 2026; resort pages have none).
 * The path points at the largest WebP copy; smaller copies sit beside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('islands', function (Blueprint $t) {
            $t->string('header_image_path', 255)->nullable();
            $t->string('header_image_alt', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('islands', function (Blueprint $t) {
            $t->dropColumn(['header_image_path', 'header_image_alt']);
        });
    }
};
