<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Property types become editable by super admins (agreed 19 Nov 2026): a property_types
 * table, and listings.property_type changes from a fixed ENUM to a code that matches
 * property_types.code. The six original types are added here so every existing listing
 * keeps a valid type.
 */
return new class extends Migration
{
    public const DEFAULTS = [
        ['code' => 'villa',     'name' => ['en' => 'Villa',     'es' => 'Villa',        'de' => 'Villa']],
        ['code' => 'apartment', 'name' => ['en' => 'Apartment', 'es' => 'Apartamento',  'de' => 'Ferienwohnung']],
        ['code' => 'townhouse', 'name' => ['en' => 'Townhouse', 'es' => 'Casa adosada', 'de' => 'Stadthaus']],
        ['code' => 'finca',     'name' => ['en' => 'Finca',     'es' => 'Finca',        'de' => 'Finca']],
        ['code' => 'bungalow',  'name' => ['en' => 'Bungalow',  'es' => 'Bungaló',      'de' => 'Bungalow']],
        ['code' => 'studio',    'name' => ['en' => 'Studio',    'es' => 'Estudio',      'de' => 'Studio']],
    ];

    public function up(): void
    {
        Schema::create('property_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 30)->unique();          // used in listings and in search addresses; never changes
            $table->json('name');                          // {"en": "...", "es": "...", "de": "..."}
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);   // switched off: not offered for new listings or in search filters
        });

        foreach (self::DEFAULTS as $i => $type) {
            DB::table('property_types')->insert([
                'code' => $type['code'],
                'name' => json_encode($type['name'], JSON_UNESCAPED_UNICODE),
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);
        }

        Schema::table('listings', function (Blueprint $table) {
            $table->string('property_type', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->enum('property_type', array_column(self::DEFAULTS, 'code'))->change();
        });
        Schema::dropIfExists('property_types');
    }
};
