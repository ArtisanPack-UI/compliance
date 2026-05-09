<?php

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
