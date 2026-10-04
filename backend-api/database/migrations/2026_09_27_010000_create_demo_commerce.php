<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->timestamp('created_at');
            $t->unique(['user_id', 'vehicle_id']);
        });
        Schema::create('rental_quotes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('shop_timezone', 64);
            $t->unsignedSmallInteger('billable_days');
            $t->unsignedBigInteger('daily_price_minor');
            $t->unsignedBigInteger('total_minor');
            $t->char('currency_code', 3);
            $t->foreign('currency_code')->references('code')->on('currencies');
            $t->unsignedTinyInteger('minor_unit');
            $t->unsignedInteger('vehicle_version');
            $t->dateTime('expires_at');
            $t->dateTime('consumed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 40)->unique();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->foreignId('shop_id')->constrained()->restrictOnDelete();
            $t->foreignId('quote_id')->unique()->constrained('rental_quotes')->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('shop_timezone', 64);
            $t->unsignedSmallInteger('billable_days');
            $t->unsignedBigInteger('daily_price_minor');
            $t->unsignedBigInteger('subtotal_minor');
            $t->unsignedBigInteger('fees_minor')->default(0);
            $t->unsignedBigInteger('total_minor');
            $t->char('currency_code', 3);
            $t->foreign('currency_code')->references('code')->on('currencies');
            $t->unsignedTinyInteger('minor_unit');
            $t->enum('status', ['pending', 'confirmed', 'active', 'completed', 'cancelled', 'rejected', 'expired']);
            $t->dateTime('expires_at')->nullable();
            $t->string('payment_method_demo', 32);
            $t->string('conditions_version', 32);
            $t->unsignedInteger('version')->default(1);
            $t->boolean('is_demo')->default(true);
            $t->json('vehicle_snapshot');
            $t->json('seller_snapshot');
            $t->timestamps();
            $t->index(['vehicle_id', 'status', 'starts_at']);
            $t->index(['user_id', 'created_at']);
            $t->index(['shop_id', 'status', 'created_at']);
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 40)->unique();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->foreignId('shop_id')->constrained()->restrictOnDelete();
            $t->foreignId('reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->enum('kind', ['sale', 'rental']);
            $t->enum('status', ['pending', 'confirmed', 'fulfilled', 'cancelled']);
            $t->unsignedBigInteger('subtotal_minor');
            $t->unsignedBigInteger('fees_minor')->default(0);
            $t->unsignedBigInteger('total_minor');
            $t->char('currency_code', 3);
            $t->foreign('currency_code')->references('code')->on('currencies');
            $t->unsignedTinyInteger('minor_unit');
            $t->string('payment_method_demo', 32);
            $t->boolean('is_demo')->default(true);
            $t->json('vehicle_snapshot');
            $t->json('seller_snapshot');
            $t->string('conditions_version', 32);
            $t->dateTime('expires_at')->nullable();
            $t->dateTime('confirmed_at')->nullable();
            $t->dateTime('fulfilled_at')->nullable();
            $t->string('cancellation_reason', 80)->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
            $t->index(['shop_id', 'status', 'created_at']);
        });
        Schema::create('vehicle_blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->foreignId('reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->enum('kind', ['hold', 'rental', 'sale', 'maintenance']);
            $t->dateTime('starts_at');
            $t->dateTime('ends_at')->nullable();
            $t->dateTime('expires_at')->nullable();
            $t->dateTime('released_at')->nullable();
            $t->string('reason', 80)->nullable();
            $t->timestamps();
            $t->index(['vehicle_id', 'starts_at', 'ends_at']);
        });
        DB::statement("ALTER TABLE vehicle_blocks ADD CONSTRAINT blocks_owner_check CHECK ((kind = 'hold' AND ((reservation_id IS NULL) <> (order_id IS NULL)) AND expires_at IS NOT NULL) OR (kind = 'rental' AND reservation_id IS NOT NULL AND order_id IS NULL AND ends_at IS NOT NULL) OR (kind = 'sale' AND order_id IS NOT NULL AND reservation_id IS NULL AND ends_at IS NULL) OR (kind = 'maintenance' AND order_id IS NULL AND reservation_id IS NULL))");
        DB::statement('ALTER TABLE vehicle_blocks ADD CONSTRAINT blocks_interval_check CHECK (ends_at IS NULL OR ends_at > starts_at)');
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 40)->unique();
            $t->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $t->string('provider', 20)->default('demo');
            $t->string('provider_reference', 64)->nullable();
            $t->string('method', 32);
            $t->unsignedBigInteger('amount_minor');
            $t->char('currency_code', 3);
            $t->foreign('currency_code')->references('code')->on('currencies');
            $t->unsignedTinyInteger('minor_unit');
            $t->enum('status', ['initiated', 'pending', 'paid', 'failed', 'cancelled']);
            $t->boolean('is_demo')->default(true);
            $t->dateTime('paid_at')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'provider_reference']);
        });
        Schema::create('idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('scope', 32);
            $t->string('key', 80);
            $t->char('request_hash', 64);
            $t->unsignedBigInteger('resource_id')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'scope', 'key']);
        });
        Schema::create('commerce_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('resource_type', 32);
            $t->unsignedBigInteger('resource_id');
            $t->string('action', 40);
            $t->boolean('is_demo')->default(true);
            $t->timestamp('created_at');
            $t->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        foreach (['commerce_events', 'idempotency_keys', 'payments', 'vehicle_blocks', 'orders', 'reservations', 'rental_quotes', 'favorites'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
