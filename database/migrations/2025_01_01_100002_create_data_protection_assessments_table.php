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
        Schema::create('data_protection_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('assessment_number', 20)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('processing_activity_id')->nullable();
        
        $table->foreign('processing_activity_id')
              ->references('id')
              ->on('processing_activities')
              ->onDelete('set null');
            $table->enum('status', ['draft', 'pending', 'in_review', 'approved', 'rejected', 'revision_required'])->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('parent_assessment_id')->nullable();
            $table->foreign('parent_assessment_id')
                ->references('id')
                ->on('data_protection_assessments')
                ->onDelete('set null');
            $table->json('data_categories')->nullable();
            $table->json('data_subjects')->nullable();
            $table->json('processing_purposes')->nullable();
            $table->json('legal_bases')->nullable();
            $table->json('recipients')->nullable();
            $table->json('retention_periods')->nullable();
            $table->json('transfers')->nullable();
            $table->json('security_measures')->nullable();
            $table->decimal('overall_risk_score', 5, 2)->nullable();
            $table->enum('overall_risk_level', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->text('dpo_opinion')->nullable();
            $table->timestamp('dpo_reviewed_at')->nullable();
            $table->unsignedBigInteger('dpo_reviewed_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('next_review_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('processing_activity_id');
            $table->index('overall_risk_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_protection_assessments');
    }
};
