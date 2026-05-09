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
        Schema::create('compliance_check_results', function (Blueprint $table) {
            $table->id();
            $table->string('check_name', 100);
            $table->enum('status', ['passed', 'failed', 'warning', 'error']);
            $table->decimal('score', 5, 2)->nullable();
            $table->unsignedInteger('violations_found')->default(0);
            $table->unsignedInteger('warnings_found')->default(0);
            $table->unsignedInteger('items_checked')->default(0);
            $table->unsignedInteger('items_compliant')->default(0);
            $table->json('details')->nullable();
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('check_name');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_check_results');
    }
};
