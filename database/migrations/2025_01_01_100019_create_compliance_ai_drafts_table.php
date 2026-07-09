<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create( 'compliance_ai_drafts', function ( Blueprint $table ): void {
            $table->id();
            $table->string( 'feature_key', 128 )->index();
            $table->string( 'subject_key', 191 )->nullable()->index();
            $table->json( 'content' );
            $table->json( 'metadata' )->nullable();
            $table->unsignedBigInteger( 'created_by' )->nullable()->index();
            $table->timestamp( 'created_at' )->useCurrent();
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'compliance_ai_drafts' );
    }
};
