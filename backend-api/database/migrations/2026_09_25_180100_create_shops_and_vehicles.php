<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $t) {
            $t->id();
            $t->foreignId('merchant_id')->constrained('merchant_profiles')->restrictOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 16)->nullable();
            $t->string('address');
            $t->char('country_code', 2);
            $t->foreign('country_code')->references('code')->on('countries');
            $t->foreignId('city_id')->constrained();
            $t->foreignId('district_id')->nullable()->constrained();
            $t->char('currency_code', 3);
            $t->foreign('currency_code')->references('code')->on('currencies');
            $t->string('timezone', 64);
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('logo_path')->nullable();
            $t->string('cover_path')->nullable();
            $t->enum('status', ['draft', 'published', 'suspended'])->default('draft');
            $t->boolean('is_demo')->default(false);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['merchant_id', 'status', 'deleted_at']);
        });
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shop_id')->constrained()->restrictOnDelete();
            $t->string('reference', 40)->unique();
            $t->string('slug')->unique();
            $t->foreignId('vehicle_model_id')->constrained();
            $t->foreignId('category_id')->constrained();
            $t->string('title');
            $t->string('trim')->nullable();
            $t->unsignedSmallInteger('year');
            $t->enum('condition', ['new', 'used']);
            $t->boolean('is_for_sale')->default(false);
            $t->boolean('is_for_rent')->default(false);
            $t->unsignedBigInteger('sale_price_minor')->nullable();
            $t->unsignedBigInteger('rent_daily_minor')->nullable();
            $t->char('currency_code', 3);
            $t->foreign('currency_code')->references('code')->on('currencies');
            $t->unsignedInteger('mileage_km')->nullable();
            $t->enum('fuel', ['petrol', 'diesel', 'hybrid', 'electric']);
            $t->enum('transmission', ['manual', 'automatic']);
            $t->string('engine')->nullable();
            $t->unsignedSmallInteger('horsepower')->nullable();
            $t->unsignedTinyInteger('doors')->nullable();
            $t->unsignedTinyInteger('seats')->nullable();
            $t->string('color', 80)->nullable();
            $t->string('vin', 17)->nullable();
            $t->string('license_plate', 32)->nullable();
            $t->text('description');
            $t->char('country_code', 2);
            $t->foreign('country_code')->references('code')->on('countries');
            $t->foreignId('city_id')->constrained();
            $t->foreignId('district_id')->nullable()->constrained();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->enum('publication_status', ['draft', 'published', 'archived'])->default('draft');
            $t->enum('inventory_status', ['available', 'rented', 'sold', 'other'])->default('available');
            $t->boolean('is_featured')->default(false);
            $t->boolean('is_certified')->default(false);
            $t->boolean('is_demo')->default(false);
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['shop_id', 'inventory_status', 'deleted_at']);
            $t->index(['publication_status', 'published_at']);
            $t->index(['is_for_sale', 'is_for_rent']);
        });
        Schema::create('vehicle_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->string('storage_key')->unique();
            $t->string('alt_text')->nullable();
            $t->unsignedInteger('position');
            $t->boolean('is_placeholder')->default(false);
            $t->timestamps();
            $t->unique(['vehicle_id', 'position']);
        });
        Schema::create('feature_vehicle', function (Blueprint $t) {
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->foreignId('feature_id')->constrained()->restrictOnDelete();
            $t->primary(['vehicle_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        foreach (['feature_vehicle', 'vehicle_images', 'vehicles', 'shops'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
