<?php

/**
 * ProcessErasureRequestJob queueable job.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Jobs;

use ArtisanPackUI\Compliance\Compliance\Erasure\ErasureService;
use ArtisanPackUI\Compliance\Models\ErasureRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessErasureRequestJob implements ShouldQueue
{
    use Dispatchable;
use InteractsWithQueue;
use Queueable;
use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    public function __construct( public ErasureRequest $request )
    {
    }

    /**
     * Execute the job.
     */
    public function handle( ErasureService $erasureService ): void
    {
        $erasureService->processRequest( $this->request );
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'compliance',
            'erasure',
            'request:' . $this->request->request_number,
        ];
    }
}
