<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Events;

use ArtisanPackUI\Compliance\Models\PortabilityRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DataExportCompleted
{
    use Dispatchable;
use SerializesModels;

    public function __construct( public PortabilityRequest $request )
    {
    }
}
