<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geography group: islands, areas. Also island_admins, which needs both users and islands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('islands', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('slug', 60)->unique();
            $table->json('name');                 // {"en":"Menorca","es":"Menorca","de":"Menorca"}
            $table->json('intro')->nullable();
            $table->decimal('centre_lat', 9, 6)->nullable();
            $table->decimal('centre_lng', 9, 6)->nullable();
            $table->boolean('is_active')->default(false);
            $table->smallInteger('sort_order')->default(0);
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('island_id');
            $table->string('slug', 80);
            $table->enum('kind', ['beach_resort', 'coastal_resort', 'town']);
            $table->json('name');
            $table->json('intro')->nullable();
            $table->decimal('centre_lat', 9, 6)->nullable();
            $table->decimal('centre_lng', 9, 6)->nullable();
            $table->smallInteger('sort_order')->default(0);

            $table->unique(['island_id', 'slug'], 'uq_areas_slug');
            $table->foreign('island_id', 'fk_areas_island')->references('id')->on('islands');
        });

        Schema::create('island_admins', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('island_id');
            $table->unsignedBigInteger('assigned_by');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'island_id']);
            $table->index('island_id', 'idx_island_admins_island');
            $table->foreign('user_id', 'fk_ia_user')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('island_id', 'fk_ia_island')->references('id')->on('islands');
            $table->foreign('assigned_by', 'fk_ia_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('island_admins');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('islands');
    }
};
