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
        Schema::create('compliance_scores', function (Blueprint $table) {
            $table->id();
            $table->decimal('overall_score', 5, 2);
            $table->string('regulation', 50)->default('all');
            $table->json('category_scores')->nullable();
            $table->json('findings')->nullable();
            $table->json('recommendations')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamp('next_calculation_at')->nullable();
            $table->string('calculated_by', 50)->nullable();
            $table->timestamps();

            $table->index('regulation');
            $table->index('calculated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_scores');
    }
};
