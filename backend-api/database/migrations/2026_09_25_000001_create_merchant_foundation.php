<?php

use App\Enums\MembershipRole;
use App\Enums\MerchantApproval;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('legal_name');
            $table->string('display_name');
            $table->enum('approval_status', array_column(MerchantApproval::cases(), 'value'))->default(MerchantApproval::PENDING->value);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('merchant_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchant_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('role', array_column(MembershipRole::cases(), 'value'));
            $table->unique(['merchant_id', 'user_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_memberships');
        Schema::dropIfExists('merchant_profiles');
    }
};
