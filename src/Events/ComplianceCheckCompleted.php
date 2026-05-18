<?php

/**
 * ComplianceCheckCompleted domain event.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Events;

use ArtisanPackUI\Compliance\Models\ComplianceCheckResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComplianceCheckCompleted
{
    use Dispatchable;
use SerializesModels;

    public function __construct( public ComplianceCheckResult $result )
    {
    }
}
