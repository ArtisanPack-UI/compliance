<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Events;

use ArtisanPackUI\Compliance\Models\ComplianceViolation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComplianceViolationDetected
{
    use Dispatchable;
use SerializesModels;

    public function __construct( public ComplianceViolation $violation )
    {
    }
}
