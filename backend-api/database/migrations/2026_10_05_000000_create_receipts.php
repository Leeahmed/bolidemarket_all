<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['orders', 'reservations'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->json('buyer_snapshot')->nullable());
        }
        Schema::create('receipts', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 64)->unique();
            $t->foreignId('payment_id')->unique()->constrained();
            $t->foreignId('order_id')->unique()->constrained();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('shop_id')->constrained();
            $t->string('type', 12);
            $t->char('currency_code', 3);
            $t->unsignedTinyInteger('minor_unit');
            foreach (['subtotal_minor', 'fees_minor', 'total_minor'] as $c) {
                $t->unsignedBigInteger($c);
            }
            $t->string('payment_method', 32);
            $t->string('payment_status', 20);
            $t->boolean('is_demo');
            $t->timestamp('issued_at');
            foreach (['buyer_snapshot', 'seller_snapshot', 'vehicle_snapshot', 'transaction_snapshot'] as $c) {
                $t->json($c);
            }
            $t->timestamps();
            $t->index(['user_id', 'issued_at']);
            $t->index(['shop_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        foreach (['orders', 'reservations'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('buyer_snapshot'));
        }
    }
};
