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
        Schema::create('erasure_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 20)->unique();
            $table->unsignedBigInteger('user_id');
            $table->enum('requester_type', ['self', 'guardian', 'authorized_agent'])->default('self');
            $table->string('requester_contact')->nullable();
            $table->enum('status', ['pending', 'verifying', 'approved', 'processing', 'completed', 'rejected'])->default('pending');
            $table->enum('scope', ['full', 'partial'])->default('full');
            $table->json('specific_data')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('identity_verified')->default(false);
            $table->timestamp('identity_verified_at')->nullable();
            $table->string('identity_verified_method', 50)->nullable();
            $table->json('exemptions_found')->nullable();
            $table->text('exemption_explanation')->nullable();
            $table->json('handlers_processed')->nullable();
            $table->json('handlers_failed')->nullable();
            $table->json('third_parties_notified')->nullable();
            $table->string('certificate_path', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('deadline_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('deadline_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erasure_requests');
    }
};
