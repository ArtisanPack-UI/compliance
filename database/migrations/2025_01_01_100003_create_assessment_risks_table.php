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
        Schema::create('assessment_risks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assessment_id');
            $table->string('risk_category', 50);
            $table->string('risk_title');
            $table->text('risk_description')->nullable();
            $table->enum('likelihood', ['rare', 'unlikely', 'possible', 'likely', 'almost_certain']);
            $table->enum('impact', ['negligible', 'minor', 'moderate', 'major', 'severe']);
            $table->decimal('inherent_score', 5, 2)->nullable();
            $table->decimal('residual_score', 5, 2)->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->unsignedBigInteger('risk_owner')->nullable();
            $table->enum('status', ['identified', 'mitigating', 'mitigated', 'accepted'])->default('identified');
            $table->unsignedBigInteger('accepted_by')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->text('acceptance_justification')->nullable();
            $table->timestamps();

            $table->index('assessment_id');
            $table->index('risk_level');

            $table->foreign('assessment_id')
                ->references('id')
                ->on('data_protection_assessments')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_risks');
    }
};
