<?php

/**
 * Main Compliance class.
 *
 * Resolved from the container as `compliance` and via the
 * {@see compliance()} helper. Once content extraction lands, public
 * functionality will be exposed via dedicated services; until then
 * this class is a version-reporting shim.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @since      0.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance;

class Compliance
{
    public function version(): string
    {
        return '0.1.0';
    }
}
