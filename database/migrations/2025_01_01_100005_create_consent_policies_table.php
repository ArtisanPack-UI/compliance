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
        Schema::create('consent_policies', function (Blueprint $table) {
            $table->id();
            $table->string('purpose', 100);
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('legal_text');
            $table->string('version', 20);
            $table->unsignedBigInteger('previous_version_id')->nullable();
            $table->json('data_categories')->nullable();
            $table->json('processing_details')->nullable();
            $table->string('retention_period', 100)->nullable();
            $table->json('third_party_sharing')->nullable();
            $table->text('rights_description')->nullable();
            $table->text('withdrawal_consequences')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_explicit')->default(true);
            $table->unsignedTinyInteger('minimum_age')->default(16);
            $table->timestamp('effective_at');
            $table->timestamp('expires_at')->nullable();
            $table->json('changes_from_previous')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['purpose', 'version']);
            $table->index(['is_active', 'purpose']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consent_policies');
    }
};
