<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Jobs;

use ArtisanPackUI\Compliance\Compliance\Monitoring\ComplianceMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunComplianceChecksJob implements ShouldQueue
{
    use Dispatchable;
use InteractsWithQueue;
use Queueable;
use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    public function __construct( public ?string $category = null )
    {
    }

    /**
     * Execute the job.
     */
    public function handle( ComplianceMonitor $monitor ): void
    {
        if ( $this->category ) {
            $monitor->runChecksByCategory( $this->category );
        } else {
            $monitor->runAllChecks();
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return ['compliance', 'checks'];
    }
}
