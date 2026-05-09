<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Events;

use ArtisanPackUI\Compliance\Models\ConsentRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConsentWithdrawn
{
    use Dispatchable;
use SerializesModels;

    public function __construct( public ConsentRecord $consent )
    {
    }
}
