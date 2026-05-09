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
        Schema::create('risk_mitigations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('risk_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['technical', 'organizational', 'contractual']);
            $table->enum('status', ['planned', 'in_progress', 'implemented', 'verified'])->default('planned');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('implemented_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->unsignedTinyInteger('effectiveness_rating')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('risk_id');
            $table->index('status');

            $table->foreign('risk_id')
                ->references('id')
                ->on('assessment_risks')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risk_mitigations');
    }
};
