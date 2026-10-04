<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->char('country_code', 2)->nullable();
            $t->foreign('country_code')->references('code')->on('countries')->restrictOnDelete();
            $t->foreignId('city_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('district_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('avatar_path')->nullable();
        });
        Schema::table('shops', fn (Blueprint $t) => $t->string('activity_type', 16)->default('both'));
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $t) => $t->dropColumn('activity_type'));
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['country_code']);
            $t->dropConstrainedForeignId('city_id');
            $t->dropConstrainedForeignId('district_id');
            $t->dropColumn(['country_code', 'avatar_path']);
        });
    }
};
