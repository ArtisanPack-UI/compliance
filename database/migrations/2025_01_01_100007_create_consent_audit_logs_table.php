<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('consent_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consent_record_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->enum('action', ['granted', 'withdrawn', 'expired', 'policy_updated']);
            $table->string('purpose', 100);
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->string('policy_version', 20)->nullable();
            $table->enum('actor_type', ['user', 'system', 'admin']);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('consent_record_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consent_audit_logs');
    }
};
