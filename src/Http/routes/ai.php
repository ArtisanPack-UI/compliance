<?php

declare( strict_types=1 );

/**
 * Compliance AI API routes.
 *
 * Mounted at `/api/v1/compliance/ai` by ComplianceServiceProvider so
 * React and Vue front-ends can trigger the three compliance.* AI
 * agents (privacy policy draft, DPIA assistance, consent text) via
 * plain HTTP.
 *
 * @since 1.1.0
 */

use ArtisanPackUI\Compliance\Http\Controllers\Ai\AiController;
use Illuminate\Support\Facades\Route;

// Guard is configurable via `artisanpack.compliance.ai.guard` — defaults
// to `sanctum` because the parent group is mounted under the `api`
// middleware stack, but consumer apps without Sanctum can point it at
// `web` (session auth) or any other configured guard.
$guard = config( 'artisanpack.compliance.ai.guard', 'sanctum' );

Route::middleware( 'auth:' . $guard )->group( function (): void {
    Route::get( '/features', [ AiController::class, 'features' ] );
    Route::post( '/privacy-policy-draft', [ AiController::class, 'privacyPolicyDraft' ] );
    Route::post( '/dpia-assistance', [ AiController::class, 'dpiaAssistance' ] );
    Route::post( '/consent-text', [ AiController::class, 'consentText' ] );
} );
