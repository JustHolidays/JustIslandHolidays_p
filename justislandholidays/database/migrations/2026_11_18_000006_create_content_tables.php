<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content and operations group: pages, page_translations, audit_log, site_settings, hero_images.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120);
            $table->unsignedSmallInteger('island_id')->nullable();
            $table->enum('kind', ['static', 'guide']);
            $table->boolean('is_published')->default(false);
            // Slugs are unique per island, and unique among site-wide pages (NULL island)
            $table->unsignedSmallInteger('island_key')->virtualAs('IFNULL(island_id, 0)');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['island_key', 'slug'], 'uq_pages_slug');
            $table->foreign('island_id', 'fk_pages_island')->references('id')->on('islands');
        });

        Schema::create('page_translations', function (Blueprint $table) {
            $table->unsignedBigInteger('page_id');
            $table->enum('lang', ['en', 'es', 'de']);
            $table->string('title', 190);
            $table->mediumText('body_md');
            $table->string('meta_title', 190)->nullable();
            $table->string('meta_description', 300)->nullable();

            $table->primary(['page_id', 'lang']);
            $table->foreign('page_id', 'fk_pt_page')->references('id')->on('pages')->cascadeOnDelete();
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();      // NULL for system actions
            $table->string('action', 60);                            // 'listing.approve', 'licence.verify'
            $table->string('entity', 60);
            $table->string('entity_id', 40)->nullable();
            $table->json('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity', 'entity_id', 'created_at'], 'idx_audit_entity');
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->string('setting_key', 60)->primary();            // 'theme.colours', 'home.hero_mode'
            $table->json('value');
            $table->json('draft_value')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('updated_by', 'fk_settings_user')->references('id')->on('users');
        });

        Schema::create('hero_images', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('image_path', 255);
            $table->string('alt_text', 160);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['is_active', 'sort_order'], 'idx_hero_order');
            $table->foreign('uploaded_by', 'fk_hero_user')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_images');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('pages');
    }
};
