<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE merchant_profiles MODIFY approval_status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending'");
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('moderation_status', 20)->default('clear')->index();
        });
        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 30);
            $table->unsignedBigInteger('subject_id');
            $table->string('reason', 500);
            $table->json('metadata');
            $table->timestamp('created_at');
            $table->index(['subject_type', 'subject_id', 'created_at']);
        });
    }

    public function down(): void
    {
        // A downgrade must never silently turn a suspended merchant into an approved one.
        if (DB::table('merchant_profiles')->where('approval_status', 'suspended')->exists()) {
            throw new RuntimeException('Réactiver ou rejeter les professionnels suspendus avant rollback.');
        }
        Schema::dropIfExists('admin_activity_logs');
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn('moderation_status'));
        DB::statement("ALTER TABLE merchant_profiles MODIFY approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
    }
};
