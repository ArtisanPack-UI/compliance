<?php

/**
 * JSON API controller for the compliance AI trigger surface.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Http\Controllers\Ai;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\Compliance\Ai\Agents\ConsentTextSuggestionAgent;
use ArtisanPackUI\Compliance\Ai\Agents\DpiaAssistanceAgent;
use ArtisanPackUI\Compliance\Ai\Agents\PrivacyPolicyDraftAgent;
use ArtisanPackUI\Compliance\ComplianceServiceProvider;
use ArtisanPackUI\Compliance\Http\Requests\Ai\ConsentTextRequest;
use ArtisanPackUI\Compliance\Http\Requests\Ai\DpiaAssistanceRequest;
use ArtisanPackUI\Compliance\Http\Requests\Ai\PrivacyPolicyDraftRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * REST surface for React / Vue / any non-Livewire host. Kept
 * framework-agnostic — front-end packages consume `/api/v1/compliance/ai/*`
 * directly and never need to know about the individual agents.
 *
 * Feature-toggle enforcement lives inside the agents. This controller
 * only wraps errors in a consistent JSON envelope.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @since      1.1.0
 */
class AiController
{
    /**
     * Return the enabled state of the three compliance.* features so a
     * front-end can decide which affordances to render.
     */
    public function features(): JsonResponse
    {
        /** @var FeatureRegistry $registry */
        $registry = app( FeatureRegistry::class );

        $state = [];
        foreach ( ComplianceServiceProvider::AI_FEATURE_KEYS as $key ) {
            $state[ $key ] = null !== $registry->get( $key ) && $registry->isToggleOn( $key );
        }

        return new JsonResponse( [ 'features' => $state ] );
    }

    /**
     * POST /privacy-policy-draft.
     */
    public function privacyPolicyDraft( PrivacyPolicyDraftRequest $request ): JsonResponse
    {
        return $this->runAgent(
            'compliance.privacy_policy_draft',
            fn () => PrivacyPolicyDraftAgent::for( $request->validated() )->run(),
        );
    }

    /**
     * POST /dpia-assistance.
     */
    public function dpiaAssistance( DpiaAssistanceRequest $request ): JsonResponse
    {
        return $this->runAgent(
            'compliance.dpia_assistance',
            fn () => DpiaAssistanceAgent::for( $request->validated() )->run(),
        );
    }

    /**
     * POST /consent-text.
     */
    public function consentText( ConsentTextRequest $request ): JsonResponse
    {
        return $this->runAgent(
            'compliance.consent_text',
            fn () => ConsentTextSuggestionAgent::for( $request->validated() )->run(),
        );
    }

    /**
     * Shared wrapper — normalizes agent exceptions into consistent
     * JSON envelopes.
     */
    private function runAgent( string $featureKey, callable $callback ): JsonResponse
    {
        try {
            $output = $callback();
            return new JsonResponse( [
                'feature' => $featureKey,
                'output'  => $output,
            ] );
        } catch ( FeatureDisabledException $e ) {
            return new JsonResponse( [
                'feature' => $featureKey,
                'error'   => 'feature_disabled',
                'message' => $e->getMessage(),
            ], 403 );
        } catch ( MissingCredentialsException $e ) {
            return new JsonResponse( [
                'feature' => $featureKey,
                'error'   => 'missing_credentials',
                'message' => $e->getMessage(),
            ], 503 );
        } catch ( FeatureError $e ) {
            return new JsonResponse( [
                'feature' => $featureKey,
                'error'   => 'invalid_input',
                'message' => $e->getMessage(),
            ], 422 );
        } catch ( Throwable $e ) {
            Log::error( 'compliance AI API call failed', [
                'feature' => $featureKey,
                'error'   => $e->getMessage(),
            ] );
            return new JsonResponse( [
                'feature' => $featureKey,
                'error'   => 'internal_error',
                'message' => 'Unexpected error running AI feature.',
            ], 500 );
        }
    }
}
