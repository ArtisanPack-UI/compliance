<?php

declare( strict_types=1 );

namespace Tests\Feature\Ai;

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Compliance\Ai\Agents\DpiaAssistanceAgent;

beforeEach( function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

function validDpiaInput(): array
{
    return [
        'activity_name'    => 'Employee performance analytics',
        'activity_purpose' => 'Track productivity metrics for annual reviews',
        'processing_scale' => 'medium',
        'data_categories'  => [ 'name', 'performance metrics', 'schedule data' ],
        'data_subjects'    => [ 'employees' ],
    ];
}

it( 'forces requires_legal_review and returns risks', function (): void {
    $this->prompter->queue( [
        'risks' => [
            [
                'title'             => 'Function creep',
                'description'       => 'Metrics may be repurposed for termination decisions.',
                'likelihood'        => 'medium',
                'severity'          => 'high',
                'affected_subjects' => [ 'employees' ],
            ],
        ],
        'mitigations' => [
            [
                'risk_title'    => 'Function creep',
                'mitigation'    => 'Restrict data use to review process via role-based access.',
                'residual_risk' => 'low',
            ],
        ],
        'stakeholder_impacts'   => [],
        'requires_legal_review' => false, // model lying
        'review_checklist'      => [ 'Confirm mitigations are enforced technically.' ],
    ] );

    $result = DpiaAssistanceAgent::for( validDpiaInput() )->run();

    expect( $result['requires_legal_review'] )->toBeTrue();
    expect( $result['risks'] )->toHaveCount( 1 );
    expect( $result['mitigations'][0]['risk_title'] )->toBe( 'Function creep' );
} );

it( 'backfills a review checklist when the model omits one', function (): void {
    $this->prompter->queue( [
        'risks' => [
            [
                'title'             => 'x',
                'description'       => 'y',
                'likelihood'        => 'low',
                'severity'          => 'low',
                'affected_subjects' => [ 'z' ],
            ],
        ],
        'mitigations'           => [
            [ 'risk_title' => 'x', 'mitigation' => 'a', 'residual_risk' => 'low' ],
        ],
        'stakeholder_impacts'   => [],
        'requires_legal_review' => true,
        'review_checklist'      => [],
    ] );

    $result = DpiaAssistanceAgent::for( validDpiaInput() )->run();

    expect( $result['review_checklist'] )->not->toBeEmpty();
    expect( $result['review_checklist'][0] )->toContain( 'DPO' );
} );

it( 'rejects risks that have no matching mitigation entry', function (): void {
    $this->prompter->queue( [
        'risks' => [
            [
                'title'             => 'Cross-border transfer without SCC',
                'description'       => '...',
                'likelihood'        => 'high',
                'severity'          => 'high',
                'affected_subjects' => [ 'employees' ],
            ],
        ],
        'mitigations'           => [], // linkage broken
        'stakeholder_impacts'   => [],
        'requires_legal_review' => true,
        'review_checklist'      => [ 'x' ],
    ] );

    expect( fn () => DpiaAssistanceAgent::for( validDpiaInput() )->run() )
        ->toThrow( FeatureError::class );
} );

it( 'rejects when a mitigation refers to a risk_title that does not exist', function (): void {
    $this->prompter->queue( [
        'risks' => [
            [
                'title'             => 'Real risk',
                'description'       => '...',
                'likelihood'        => 'medium',
                'severity'          => 'high',
                'affected_subjects' => [ 'employees' ],
            ],
        ],
        'mitigations' => [
            [ 'risk_title' => 'A different risk', 'mitigation' => '...', 'residual_risk' => 'low' ],
        ],
        'stakeholder_impacts'   => [],
        'requires_legal_review' => true,
        'review_checklist'      => [ 'x' ],
    ] );

    expect( fn () => DpiaAssistanceAgent::for( validDpiaInput() )->run() )
        ->toThrow( FeatureError::class );
} );

it( 'raises FeatureError when no risks are returned', function (): void {
    $this->prompter->queue( [
        'risks'                 => [],
        'mitigations'           => [],
        'stakeholder_impacts'   => [],
        'requires_legal_review' => true,
        'review_checklist'      => [ 'x' ],
    ] );

    expect( fn () => DpiaAssistanceAgent::for( validDpiaInput() )->run() )
        ->toThrow( FeatureError::class );
} );

it( 'validates required input fields', function (): void {
    $input = validDpiaInput();
    unset( $input['data_categories'] );
    expect( fn () => DpiaAssistanceAgent::for( $input )->run() )
        ->toThrow( FeatureError::class );

    $input                  = validDpiaInput();
    $input['data_subjects'] = [];
    expect( fn () => DpiaAssistanceAgent::for( $input )->run() )
        ->toThrow( FeatureError::class );
} );
