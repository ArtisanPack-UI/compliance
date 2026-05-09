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
        Schema::create('export_schemas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('category', 50);
            $table->string('version', 20);
            $table->enum('format', ['json', 'xml']);
            $table->json('schema_definition');
            $table->json('field_mappings')->nullable();
            $table->json('transformations')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category', 'version', 'format']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_schemas');
    }
};
