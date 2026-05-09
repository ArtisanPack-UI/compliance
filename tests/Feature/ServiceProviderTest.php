<?php

declare( strict_types=1 );

use ArtisanPackUI\Compliance\Compliance;

it( 'binds the compliance singleton', function (): void {
    expect( app( 'compliance' ) )->toBeInstanceOf( Compliance::class );
} );

it( 'returns the same instance on subsequent resolutions', function (): void {
    expect( app( 'compliance' ) )->toBe( app( 'compliance' ) );
} );

it( 'exposes the compliance() helper', function (): void {
    expect( compliance() )->toBeInstanceOf( Compliance::class );
} );
