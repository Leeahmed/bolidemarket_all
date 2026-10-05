<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', fn (Blueprint $t) => $t->boolean('negotiation_enabled')->default(false));
        Schema::create('price_offers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_id')->constrained();
            $t->foreignId('shop_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('status', 20)->default('pending');
            $t->unsignedBigInteger('amount_minor');
            $t->unsignedBigInteger('asking_price_minor');
            $t->char('currency_code', 3);
            $t->unsignedTinyInteger('minor_unit');
            $t->unsignedBigInteger('vehicle_version');
            $t->json('vehicle_snapshot');
            $t->json('seller_snapshot');
            $t->timestamp('expires_at');
            $t->timestamp('responded_at')->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->index(['user_id', 'vehicle_id', 'status']);
            $t->index(['shop_id', 'status']);
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->foreignId('price_offer_id')->nullable()->unique()->constrained('price_offers');
            $t->json('handover')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('price_offer_id');
            $t->dropColumn('handover');
        });
        Schema::dropIfExists('price_offers');
        Schema::table('vehicles', fn (Blueprint $t) => $t->dropColumn('negotiation_enabled'));
    }
};
