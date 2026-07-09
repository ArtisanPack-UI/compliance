<?php

declare( strict_types=1 );

namespace Tests\Feature\Ai;

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Compliance\Ai\Agents\PrivacyPolicyDraftAgent;

beforeEach( function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

function validPolicyInput(): array
{
    return [
        'organization_name'     => 'Acme Widgets',
        'contact_email'         => 'privacy@acme.example',
        'effective_date'        => '2026-08-01',
        'jurisdictions'         => [ 'EU', 'US-CA' ],
        'processing_activities' => [
            [
                'purpose'         => 'Order fulfillment',
                'data_categories' => [ 'name', 'address' ],
                'legal_basis'     => 'contract',
                'retention'       => '5 years',
            ],
        ],
    ];
}

it( 'always forces requires_legal_review to true', function (): void {
    $this->prompter->queue( [
        'policy_markdown'       => '# Privacy Policy',
        'section_headings'      => [ 'What we collect' ],
        'requires_legal_review' => false, // model lying — validator must overwrite
        'review_checklist'      => [ 'Verify retention.' ],
        'jurisdiction_notes'    => [ 'EU' => 'GDPR applies.' ],
    ] );

    $result = PrivacyPolicyDraftAgent::for( validPolicyInput() )->run();

    expect( $result['requires_legal_review'] )->toBeTrue();
} );

it( 'backfills a review checklist when the model omits one', function (): void {
    $this->prompter->queue( [
        'policy_markdown'       => '# Privacy Policy',
        'section_headings'      => [],
        'requires_legal_review' => true,
        'review_checklist'      => [], // empty — validator must supply a default
        'jurisdiction_notes'    => [],
    ] );

    $result = PrivacyPolicyDraftAgent::for( validPolicyInput() )->run();

    expect( $result['review_checklist'] )->not->toBeEmpty();
    expect( $result['review_checklist'][0] )->toContain( 'legal counsel' );
} );

it( 'raises FeatureError on an empty policy body', function (): void {
    $this->prompter->queue( [
        'policy_markdown'       => '',
        'section_headings'      => [],
        'requires_legal_review' => true,
        'review_checklist'      => [ 'x' ],
        'jurisdiction_notes'    => [],
    ] );

    expect( fn () => PrivacyPolicyDraftAgent::for( validPolicyInput() )->run() )
        ->toThrow( FeatureError::class );
} );

it( 'validates required input fields', function (): void {
    expect( fn () => PrivacyPolicyDraftAgent::for( 'not-an-array' )->run() )
        ->toThrow( FeatureError::class );

    $input = validPolicyInput();
    unset( $input['organization_name'] );
    expect( fn () => PrivacyPolicyDraftAgent::for( $input )->run() )
        ->toThrow( FeatureError::class );

    $input                  = validPolicyInput();
    $input['contact_email'] = 'not-an-email';
    expect( fn () => PrivacyPolicyDraftAgent::for( $input )->run() )
        ->toThrow( FeatureError::class );

    $input                   = validPolicyInput();
    $input['effective_date'] = '08/01/2026';
    expect( fn () => PrivacyPolicyDraftAgent::for( $input )->run() )
        ->toThrow( FeatureError::class );

    $input                          = validPolicyInput();
    $input['processing_activities'] = [];
    expect( fn () => PrivacyPolicyDraftAgent::for( $input )->run() )
        ->toThrow( FeatureError::class );
} );

it( 'rejects processing_activities items that are not objects', function (): void {
    $input                          = validPolicyInput();
    $input['processing_activities'] = [
        'purpose only string',
        [
            'purpose'         => 'Order fulfillment',
            'data_categories' => [ 'name' ],
            'legal_basis'     => 'contract',
            'retention'       => '5 years',
        ],
    ];

    expect( fn () => PrivacyPolicyDraftAgent::for( $input )->run() )
        ->toThrow( FeatureError::class );
} );
