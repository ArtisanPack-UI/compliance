<?php

declare( strict_types=1 );

namespace Tests\Feature\Ai;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Compliance\Ai\Agents\ConsentTextSuggestionAgent;
use ArtisanPackUI\Compliance\Ai\Agents\DpiaAssistanceAgent;
use ArtisanPackUI\Compliance\Ai\Agents\PrivacyPolicyDraftAgent;
use ArtisanPackUI\Compliance\ComplianceServiceProvider;

it( 'declares three compliance.* features from the service provider', function (): void {
    $provider = new ComplianceServiceProvider( $this->app );
    $features = $provider->aiFeatures();

    expect( $features )->toHaveKeys( [
        'compliance.privacy_policy_draft',
        'compliance.dpia_assistance',
        'compliance.consent_text',
    ] );

    expect( $features['compliance.privacy_policy_draft']['agent'] )->toBe( PrivacyPolicyDraftAgent::class );
    expect( $features['compliance.dpia_assistance']['agent'] )->toBe( DpiaAssistanceAgent::class );
    expect( $features['compliance.consent_text']['agent'] )->toBe( ConsentTextSuggestionAgent::class );

    foreach ( $features as $definition ) {
        expect( $definition['package'] )->toBe( 'artisanpack-ui/compliance' );
    }
} );

it( 'registers the three features with the FeatureRegistry at boot', function (): void {
    /** @var FeatureRegistry $registry */
    $registry = $this->app->make( FeatureRegistry::class );

    foreach ( ComplianceServiceProvider::AI_FEATURE_KEYS as $key ) {
        expect( $registry->get( $key ) )->not->toBeNull();
    }
} );
