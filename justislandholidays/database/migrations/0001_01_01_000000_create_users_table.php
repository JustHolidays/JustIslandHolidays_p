<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts group: agencies and users (spec: Database schema > Accounts),
 * plus Laravel's password reset and session tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->string('email', 190);
            $table->string('phone', 40)->nullable();
            $table->string('website_url', 255)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->enum('role', ['owner', 'agent', 'affiliate', 'island_admin', 'super_admin'])->default('owner');
            $table->string('name', 160);
            $table->string('email', 190)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->string('phone', 40)->nullable();
            $table->unsignedBigInteger('agency_id')->nullable();
            $table->text('affiliate_data')->nullable();
            $table->enum('preferred_lang', ['en', 'es', 'de'])->default('en');
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('last_login_at')->nullable();         // shown on the Staff and Owners lists
            $table->timestamp('deactivated_at')->nullable();        // set = cannot sign in; separate from deleted_at (purged after 30 days)
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->index('agency_id', 'idx_users_agency');
            $table->foreign('agency_id', 'fk_users_agency')->references('id')->on('agencies')->nullOnDelete();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('agencies');
    }
};
