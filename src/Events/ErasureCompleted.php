<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Events;

use ArtisanPackUI\Compliance\Models\ErasureRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ErasureCompleted
{
    use Dispatchable;
use SerializesModels;

    public function __construct( public ErasureRequest $request )
    {
    }
}
