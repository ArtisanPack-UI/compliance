<?php

declare( strict_types=1 );

namespace Tests\Feature\Ai;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Compliance\Livewire\Ai\AiTools;
use ArtisanPackUI\Compliance\Models\AiDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    AiAgentTestSetup::bootstrap( $this->app );
} );

function validDraftPayload(): array
{
    return [
        'featureKey' => 'compliance.privacy_policy_draft',
        'output'     => [ 'policy_markdown' => '# hi', 'requires_legal_review' => true ],
        'subjectKey' => 'main-site',
        'metadata'   => [ 'acknowledged' => true ],
    ];
}

it( 'rejects saves when the manageComplianceAiDrafts gate denies', function (): void {
    // Default-deny gate is registered; no override → denied.
    Livewire::test( AiTools::class )
        ->dispatch( 'compliance-ai:save-draft', ...validDraftPayload() )
        ->assertDispatched( 'compliance-ai:save-draft:rejected' );

    expect( AiDraft::count() )->toBe( 0 );
} );

it( 'rejects saves when the feature is toggled off', function (): void {
    Gate::define( 'manageComplianceAiDrafts', fn ( $user = null ) => true );

    // Turn the feature OFF at the registry (config path uses literal dotted
    // keys the registry cannot address via config()->set — go through the
    // registry API instead).
    app( FeatureRegistry::class )->disable( 'compliance.privacy_policy_draft' );

    Livewire::test( AiTools::class )
        ->dispatch( 'compliance-ai:save-draft', ...validDraftPayload() )
        ->assertDispatched( 'compliance-ai:save-draft:rejected' );

    expect( AiDraft::count() )->toBe( 0 );
} );

it( 'rejects saves without the legal-review acknowledgement', function (): void {
    Gate::define( 'manageComplianceAiDrafts', fn ( $user = null ) => true );

    $payload             = validDraftPayload();
    $payload['metadata'] = []; // no acknowledged flag

    Livewire::test( AiTools::class )
        ->dispatch( 'compliance-ai:save-draft', ...$payload )
        ->assertDispatched( 'compliance-ai:save-draft:rejected' );

    expect( AiDraft::count() )->toBe( 0 );
} );

it( 'persists when gate + toggle + acknowledgement all pass', function (): void {
    Gate::define( 'manageComplianceAiDrafts', fn ( $user = null ) => true );

    Livewire::test( AiTools::class )
        ->dispatch( 'compliance-ai:save-draft', ...validDraftPayload() )
        ->assertDispatched( 'compliance-ai:save-draft:success' );

    expect( AiDraft::count() )->toBe( 1 );
} );
