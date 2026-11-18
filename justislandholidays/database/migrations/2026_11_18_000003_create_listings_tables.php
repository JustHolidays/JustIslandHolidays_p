<?php

use App\Support\Database\MySql;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listings group: listings, listing_private, listing_translations, amenities,
 * listing_amenities, listing_licences, admin_recommendations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');                  // owner, agent or affiliate who holds the listing
            $table->unsignedBigInteger('agency_id')->nullable();
            $table->unsignedSmallInteger('island_id');
            $table->unsignedInteger('area_id');
            $table->string('slug', 120);
            $table->string('public_ref', 12)->unique();              // {ref} in listing URLs and /go/{ref}
            $table->enum('property_type', ['villa', 'apartment', 'townhouse', 'finca', 'bungalow', 'studio']);
            $table->enum('status', ['draft', 'pending_review', 'live', 'paused', 'rejected', 'archived'])->default('draft');
            $table->string('rejection_reason', 500)->nullable();
            $table->unsignedTinyInteger('bedrooms');
            $table->unsignedTinyInteger('bathrooms');
            $table->unsignedTinyInteger('sleeps');
            $table->boolean('has_private_pool')->default(false);
            $table->boolean('pets_allowed')->nullable()->default(null); // NULL = not stated; hidden on the public page
            $table->unsignedInteger('distance_to_beach_m')->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('image_alt', 160)->nullable();
            $table->string('source_url', 2048)->nullable();          // admins only
            $table->string('deeplink_url', 2048)->nullable();        // https only
            $table->enum('deeplink_status', ['unchecked', 'ok', 'broken'])->default('unchecked');
            $table->timestamp('deeplink_checked_at')->nullable();
            $table->string('deeplink_subid_param', 30)->nullable();
            $table->decimal('display_lat', 9, 6);                     // offset ~300 m; the only public coordinates
            $table->decimal('display_lng', 9, 6);
            $table->char('currency', 3)->default('EUR');
            $table->unsignedInteger('min_price_week_cents')->nullable();
            $table->string('plan_code', 40)->default('free');
            $table->timestamp('featured_until')->nullable();
            $table->integer('rank_score')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->unique(['island_id', 'slug'], 'uq_listings_slug');
            $table->index(['status', 'island_id', 'area_id', 'rank_score'], 'idx_live_area');
            $table->index(['status', 'island_id', 'min_price_week_cents'], 'idx_live_price');
            $table->index(['status', 'sleeps', 'bedrooms'], 'idx_live_size');
            $table->index(['display_lat', 'display_lng'], 'idx_geo');
            $table->index('owner_id', 'idx_owner');
            $table->index('agency_id', 'idx_agency');
            $table->index(['deeplink_status', 'deeplink_checked_at'], 'idx_deeplink');
            $table->foreign('owner_id', 'fk_listings_owner')->references('id')->on('users');
            $table->foreign('agency_id', 'fk_listings_agency')->references('id')->on('agencies');
            $table->foreign('island_id', 'fk_listings_island')->references('id')->on('islands');
            $table->foreign('area_id', 'fk_listings_area')->references('id')->on('areas');
        });
        MySql::statement('ALTER TABLE listings ADD CONSTRAINT chk_sleeps CHECK (sleeps BETWEEN 1 AND 40)');
        MySql::statement("ALTER TABLE listings ADD CONSTRAINT chk_currency CHECK (currency IN ('EUR','GBP'))");
        MySql::statement("ALTER TABLE listings ADD CONSTRAINT chk_deeplink_https CHECK (deeplink_url IS NULL OR deeplink_url LIKE 'https://%')");

        // Exact location, address and contact details. Never exposed to the public.
        Schema::create('listing_private', function (Blueprint $table) {
            $table->unsignedBigInteger('listing_id')->primary();
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->string('address_line', 255)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('contact_email', 190);
            $table->string('contact_phone', 40)->nullable();
            $table->string('website_url', 255)->nullable();

            $table->foreign('listing_id', 'fk_private_listing')->references('id')->on('listings')->cascadeOnDelete();
        });

        Schema::create('listing_translations', function (Blueprint $table) {
            $table->unsignedBigInteger('listing_id');
            $table->enum('lang', ['en', 'es', 'de']);
            $table->string('title', 160);
            $table->string('summary', 500)->nullable();
            $table->boolean('is_machine_translated')->default(false);

            $table->primary(['listing_id', 'lang']);
            $table->foreign('listing_id', 'fk_trans_listing')->references('id')->on('listings')->cascadeOnDelete();
        });
        MySql::statement('ALTER TABLE listing_translations ADD FULLTEXT KEY ft_listing_text (title, summary)');

        Schema::create('amenities', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 50)->unique();
            $table->enum('category', ['essentials', 'outdoor', 'family', 'services', 'view', 'accessibility']);
            $table->json('name');
            $table->string('icon', 60)->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->smallInteger('sort_order')->default(0);
        });

        Schema::create('listing_amenities', function (Blueprint $table) {
            $table->unsignedBigInteger('listing_id');
            $table->unsignedSmallInteger('amenity_id');

            $table->primary(['listing_id', 'amenity_id']);
            $table->index(['amenity_id', 'listing_id'], 'idx_amenity_listing');
            $table->foreign('listing_id', 'fk_la_listing')->references('id')->on('listings')->cascadeOnDelete();
            $table->foreign('amenity_id', 'fk_la_amenity')->references('id')->on('amenities');
        });

        Schema::create('listing_licences', function (Blueprint $table) {
            $table->unsignedBigInteger('listing_id')->primary();
            $table->string('registration_number', 60)->nullable();
            $table->string('national_registry_no', 60)->nullable();
            $table->enum('status', ['not_provided', 'pending', 'verified', 'rejected'])->default('not_provided');
            $table->string('document_path', 255)->nullable();       // private storage
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('notes', 500)->nullable();

            $table->foreign('listing_id', 'fk_lic_listing')->references('id')->on('listings')->cascadeOnDelete();
            $table->foreign('verified_by', 'fk_lic_verifier')->references('id')->on('users');
        });

        Schema::create('admin_recommendations', function (Blueprint $table) {
            $table->id();
            $table->enum('action', ['reject_listing', 'suspend_user']);
            $table->unsignedBigInteger('listing_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedSmallInteger('island_id');
            $table->string('reason', 500);
            $table->unsignedBigInteger('recommended_by');
            $table->enum('status', ['open', 'accepted', 'declined'])->default('open');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'island_id', 'created_at'], 'idx_rec_open');
            $table->foreign('listing_id', 'fk_rec_listing')->references('id')->on('listings');
            $table->foreign('user_id', 'fk_rec_user')->references('id')->on('users');
            $table->foreign('island_id', 'fk_rec_island')->references('id')->on('islands');
            $table->foreign('recommended_by', 'fk_rec_by')->references('id')->on('users');
            $table->foreign('decided_by', 'fk_rec_decider')->references('id')->on('users');
        });
        MySql::statement('ALTER TABLE admin_recommendations ADD CONSTRAINT chk_rec_target CHECK ((listing_id IS NULL) <> (user_id IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_recommendations');
        Schema::dropIfExists('listing_licences');
        Schema::dropIfExists('listing_amenities');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('listing_translations');
        Schema::dropIfExists('listing_private');
        Schema::dropIfExists('listings');
    }
};
