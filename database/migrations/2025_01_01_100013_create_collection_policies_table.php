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
        Schema::create('collection_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('purpose', 100)->unique();
            $table->json('allowed_fields')->nullable();
            $table->json('required_fields')->nullable();
            $table->json('conditional_fields')->nullable();
            $table->json('prohibited_fields')->nullable();
            $table->text('legal_basis')->nullable();
            $table->enum('consent_type', ['explicit', 'implied', 'not_required'])->default('explicit');
            $table->json('minimization_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('purpose');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_policies');
    }
};
