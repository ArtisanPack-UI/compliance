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
        Schema::create('processing_activities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('controller_name')->nullable();
            $table->string('controller_contact')->nullable();
            $table->string('processor_name')->nullable();
            $table->string('processor_contact')->nullable();
            $table->string('dpo_contact')->nullable();
            $table->json('purposes');
            $table->json('legal_bases');
            $table->json('data_categories');
            $table->json('data_subjects');
            $table->json('recipients')->nullable();
            $table->json('third_countries')->nullable();
            $table->json('safeguards')->nullable();
            $table->json('retention_policy')->nullable();
            $table->json('security_measures')->nullable();
            $table->json('automated_decisions')->nullable();
            $table->boolean('dpia_required')->default(false);
            $table->string('dpia_reference', 100)->nullable();
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->timestamp('last_review_at')->nullable();
            $table->timestamp('next_review_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('dpia_required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processing_activities');
    }
};
