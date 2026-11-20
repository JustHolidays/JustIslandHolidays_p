<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEO titles and descriptions (agreed 20 Nov 2026), edited by super admins on Settings › SEO.
 * page_key is a page type (home, island, area, search, owners, partners) whose wording can use
 * placeholders such as {island} and {count}, or one island or resort (island:5, area:12) to
 * override it. An empty field keeps the wording built into the site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->string('page_key', 60);
            $table->char('lang', 2);
            $table->string('title', 190)->nullable();
            $table->string('description', 300)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['page_key', 'lang'], 'uq_seo_page_lang');
            $table->foreign('updated_by', 'fk_seo_user')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
