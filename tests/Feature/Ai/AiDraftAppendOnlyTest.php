<?php

declare( strict_types=1 );

namespace Tests\Feature\Ai;

use ArtisanPackUI\Compliance\Models\AiDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

uses( RefreshDatabase::class );

it( 'stores drafts with correct feature key and content payload', function (): void {
    $draft = AiDraft::create( [
        'feature_key' => 'compliance.privacy_policy_draft',
        'subject_key' => 'main-site',
        'content'     => [ 'policy_markdown' => '# Hello' ],
        'metadata'    => [ 'acknowledged' => true ],
    ] );

    expect( $draft->exists )->toBeTrue();
    expect( $draft->fresh()->content )->toBe( [ 'policy_markdown' => '# Hello' ] );
} );

it( 'blocks updates to existing drafts to preserve version history', function (): void {
    $draft = AiDraft::create( [
        'feature_key' => 'compliance.privacy_policy_draft',
        'content'     => [ 'policy_markdown' => '# Original' ],
    ] );

    $draft->content = [ 'policy_markdown' => '# Modified' ];

    expect( fn () => $draft->save() )->toThrow( RuntimeException::class );

    // Confirm nothing was persisted.
    expect( $draft->fresh()->content )->toBe( [ 'policy_markdown' => '# Original' ] );
} );

it( 'allows multiple drafts per subject to accumulate as versions', function (): void {
    AiDraft::create( [
        'feature_key' => 'compliance.privacy_policy_draft',
        'subject_key' => 'main-site',
        'content'     => [ 'v' => 1 ],
    ] );
    AiDraft::create( [
        'feature_key' => 'compliance.privacy_policy_draft',
        'subject_key' => 'main-site',
        'content'     => [ 'v' => 2 ],
    ] );

    $count = AiDraft::query()
        ->forFeature( 'compliance.privacy_policy_draft' )
        ->forSubject( 'main-site' )
        ->count();

    expect( $count )->toBe( 2 );
} );
