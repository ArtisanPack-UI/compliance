<?php

declare( strict_types=1 );

namespace Tests\Feature\Ai;

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Compliance\Ai\Agents\ConsentTextSuggestionAgent;

beforeEach( function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

function validConsentInput(): array
{
    return [
        'purpose'         => 'Send weekly product-update emails',
        'audience'        => 'general public',
        'jurisdiction'    => 'EU',
        'data_categories' => [ 'email address', 'engagement metrics' ],
    ];
}

it( 'forces requires_legal_review and truncates over-length text', function (): void {
    $longText = str_repeat( 'x', 900 );
    $this->prompter->queue( [
        'consent_text'          => $longText,
        'short_label'           => 'I agree.',
        'reading_level'         => [ 'grade' => 8.0, 'score' => 70.0 ],
        'jurisdiction_notes'    => [ 'Confirm GDPR opt-in wording.' ],
        'requires_legal_review' => false, // model lying
    ] );

    $result = ConsentTextSuggestionAgent::for( validConsentInput() )->run();

    expect( $result['requires_legal_review'] )->toBeTrue();
    expect( mb_strlen( $result['consent_text'] ) )->toBe( 800 );
} );

it( 'truncates over-length short_label', function (): void {
    $this->prompter->queue( [
        'consent_text'          => 'ok',
        'short_label'           => str_repeat( 'y', 100 ),
        'reading_level'         => [ 'grade' => 8.0, 'score' => 70.0 ],
        'jurisdiction_notes'    => [ 'x' ],
        'requires_legal_review' => true,
    ] );

    $result = ConsentTextSuggestionAgent::for( validConsentInput() )->run();

    expect( mb_strlen( $result['short_label'] ) )->toBe( 80 );
} );

it( 'raises FeatureError on empty short_label to avoid unlabelled consent checkbox', function (): void {
    $this->prompter->queue( [
        'consent_text'          => 'A perfectly good consent paragraph.',
        'short_label'           => '',
        'reading_level'         => [ 'grade' => 8.0, 'score' => 70.0 ],
        'jurisdiction_notes'    => [ 'x' ],
        'requires_legal_review' => true,
    ] );

    expect( fn () => ConsentTextSuggestionAgent::for( validConsentInput() )->run() )
        ->toThrow( FeatureError::class );
} );

it( 'raises FeatureError on empty consent text', function (): void {
    $this->prompter->queue( [
        'consent_text'          => '',
        'short_label'           => '',
        'reading_level'         => [ 'grade' => 8.0, 'score' => 70.0 ],
        'jurisdiction_notes'    => [ 'x' ],
        'requires_legal_review' => true,
    ] );

    expect( fn () => ConsentTextSuggestionAgent::for( validConsentInput() )->run() )
        ->toThrow( FeatureError::class );
} );

it( 'backfills jurisdiction_notes when the model omits them', function (): void {
    $this->prompter->queue( [
        'consent_text'          => 'ok',
        'short_label'           => 'I agree.',
        'reading_level'         => [ 'grade' => 8.0, 'score' => 70.0 ],
        'jurisdiction_notes'    => [],
        'requires_legal_review' => true,
    ] );

    $result = ConsentTextSuggestionAgent::for( validConsentInput() )->run();

    expect( $result['jurisdiction_notes'] )->not->toBeEmpty();
} );

it( 'validates required input fields', function (): void {
    $input = validConsentInput();
    unset( $input['jurisdiction'] );
    expect( fn () => ConsentTextSuggestionAgent::for( $input )->run() )
        ->toThrow( FeatureError::class );

    $input                    = validConsentInput();
    $input['data_categories'] = [];
    expect( fn () => ConsentTextSuggestionAgent::for( $input )->run() )
        ->toThrow( FeatureError::class );
} );

it( 'clamps target_reading_level to the allowed range', function (): void {
    $this->prompter->queue( [
        'consent_text'          => 'ok',
        'short_label'           => 'I agree.',
        'reading_level'         => [ 'grade' => 8.0, 'score' => 70.0 ],
        'jurisdiction_notes'    => [ 'x' ],
        'requires_legal_review' => true,
    ] );

    ConsentTextSuggestionAgent::for( array_merge( validConsentInput(), [
        'target_reading_level' => 200, // out of range — silently ignored
    ] ) )->run();

    $sentInstruction = collect( $this->prompter->calls[0]['message'] )->pluck( 'text' )->implode( "\n" );
    expect( $sentInstruction )->toContain( 'Target grade level: 8' );
} );
