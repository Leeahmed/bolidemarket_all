<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $t) {
            $t->char('code', 3)->primary();
            $t->unsignedTinyInteger('minor_unit');
            $t->boolean('active')->default(true);
        });
        Schema::create('countries', function (Blueprint $t) {
            $t->char('code', 2)->primary();
            $t->string('name');
            $t->char('currency_code', 3);
            $t->foreign('currency_code')->references('code')->on('currencies');
            $t->string('currency_symbol', 12);
            $t->string('phone_code', 8);
            $t->boolean('active')->default(true)->index();
        });
        Schema::create('cities', function (Blueprint $t) {
            $t->id();
            $t->char('country_code', 2);
            $t->foreign('country_code')->references('code')->on('countries');
            $t->string('name');
            $t->string('slug');
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->boolean('active')->default(true);
            $t->unique(['country_code', 'slug']);
            $t->index(['country_code', 'name']);
        });
        Schema::create('districts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('city_id')->constrained();
            $t->string('name');
            $t->string('slug');
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->boolean('active')->default(true);
            $t->unique(['city_id', 'slug']);
        });
        Schema::create('brands', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
        });
        Schema::create('vehicle_models', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_id')->constrained();
            $t->string('name');
            $t->unique(['brand_id', 'name']);
        });
        foreach (['categories', 'features'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('slug')->unique();
                $t->string('label');
            });
        }
    }

    public function down(): void
    {
        foreach (['features', 'categories', 'vehicle_models', 'brands', 'districts', 'cities', 'countries', 'currencies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
