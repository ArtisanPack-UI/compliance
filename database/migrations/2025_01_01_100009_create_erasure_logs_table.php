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
        Schema::create('erasure_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->string('handler_name', 100);
            $table->enum('action', ['find', 'erase', 'verify', 'rollback']);
            $table->enum('status', ['success', 'failed', 'skipped']);
            $table->unsignedInteger('records_found')->default(0);
            $table->unsignedInteger('records_erased')->default(0);
            $table->unsignedInteger('records_retained')->default(0);
            $table->text('retention_reason')->nullable();
            $table->string('backup_reference')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('request_id');

            $table->foreign('request_id')
                ->references('id')
                ->on('erasure_requests')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erasure_logs');
    }
};
