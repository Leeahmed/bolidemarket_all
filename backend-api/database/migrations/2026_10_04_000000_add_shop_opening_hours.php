<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->json('opening_hours')->nullable());
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn('opening_hours'));
    }
};
