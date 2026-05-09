<?php

/**
 * Compliance helper functions.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @since      0.1.0
 */

use ArtisanPackUI\Compliance\Compliance;

if ( ! function_exists( 'compliance' ) ) {
    /**
     * Get the Compliance instance.
     *
     * @since 0.1.0
     *
     * @return Compliance
     */
    function compliance(): Compliance
    {
        return app( 'compliance' );
    }
}
