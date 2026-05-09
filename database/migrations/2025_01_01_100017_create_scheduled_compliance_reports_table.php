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
        Schema::create('scheduled_compliance_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type', 50);
            $table->string('name', 100);
            $table->string('cron_expression', 100);
            $table->json('recipients');
            $table->json('options')->nullable();
            $table->enum('format', ['pdf', 'html', 'csv', 'json'])->default('pdf');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_compliance_reports');
    }
};
