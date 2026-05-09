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
        Schema::create('portability_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 20)->unique();
            $table->unsignedBigInteger('user_id');
            $table->enum('requester_type', ['self', 'guardian', 'authorized_agent'])->default('self');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'expired'])->default('pending');
            $table->enum('format', ['json', 'xml', 'csv'])->default('json');
            $table->json('categories')->nullable();
            $table->enum('transfer_type', ['download', 'direct_transfer'])->default('download');
            $table->string('destination_url', 500)->nullable();
            $table->boolean('destination_verified')->default(false);
            $table->string('file_path', 500)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->unsignedInteger('download_limit')->default(5);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('deadline_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portability_requests');
    }
};
