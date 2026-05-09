<?php

declare( strict_types=1 );

use ArtisanPackUI\Compliance\Compliance;

it( 'instantiates the Compliance class', function (): void {
    expect( new Compliance() )->toBeInstanceOf( Compliance::class );
} );

it( 'reports its current version', function (): void {
    expect( ( new Compliance() )->version() )->toBeString();
} );
