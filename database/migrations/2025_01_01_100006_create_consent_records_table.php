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
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('purpose', 100);
            $table->unsignedBigInteger('policy_id');
            $table->string('policy_version', 20);
            $table->enum('status', ['granted', 'withdrawn', 'expired']);
            $table->enum('consent_type', ['explicit', 'implied']);
            $table->string('collection_method', 50);
            $table->json('collection_context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('proof_reference')->nullable();
            $table->json('granular_choices')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose']);
            $table->index('status');
            $table->index('expires_at');

            $table->foreign('policy_id')
                ->references('id')
                ->on('consent_policies')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
