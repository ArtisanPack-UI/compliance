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
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('model_class')->nullable();
            $table->string('data_category', 50)->nullable();
            $table->unsignedInteger('retention_days')->nullable();
            $table->text('legal_basis')->nullable();
            $table->enum('deletion_strategy', ['delete', 'anonymize', 'archive'])->default('delete');
            $table->string('archive_location')->nullable();
            $table->json('conditions')->nullable();
            $table->json('exceptions')->nullable();
            $table->unsignedInteger('notification_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('model_class');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retention_policies');
    }
};
