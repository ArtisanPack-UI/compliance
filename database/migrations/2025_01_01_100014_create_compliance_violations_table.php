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
        Schema::create('compliance_violations', function (Blueprint $table) {
            $table->id();
            $table->string('violation_number', 20)->unique();
            $table->string('check_name', 100);
            $table->string('category', 50);
            $table->string('regulation', 50)->nullable();
            $table->string('article_reference', 50)->nullable();
            $table->enum('severity', ['info', 'low', 'medium', 'high', 'critical']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('affected_records')->nullable();
            $table->unsignedInteger('affected_count')->default(0);
            $table->json('evidence')->nullable();
            $table->json('remediation_steps')->nullable();
            $table->timestamp('remediation_deadline')->nullable();
            $table->enum('status', ['open', 'acknowledged', 'in_progress', 'resolved', 'accepted'])->default('open');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->boolean('accepted_risk')->default(false);
            $table->unsignedBigInteger('risk_acceptance_by')->nullable();
            $table->text('risk_acceptance_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index('check_name');
            $table->index('regulation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_violations');
    }
};
