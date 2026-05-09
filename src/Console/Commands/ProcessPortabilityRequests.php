<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Console\Commands;

use ArtisanPackUI\Compliance\Compliance\Portability\PortabilityService;
use ArtisanPackUI\Compliance\Models\PortabilityRequest;
use Exception;
use Illuminate\Console\Command;

class ProcessPortabilityRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:process-exports
                            {--request= : Process a specific request}
                            {--limit=10 : Maximum requests to process}
                            {--cleanup : Cleanup expired exports}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending data portability/export requests';

    public function __construct( protected PortabilityService $portabilityService )
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ( $this->option( 'cleanup' ) ) {
            return $this->cleanupExpired();
        }

        $requestNumber = $this->option( 'request' );
        $limit         = (int) $this->option( 'limit' );

        if ( $requestNumber ) {
            $request = PortabilityRequest::where( 'request_number', $requestNumber )->first();

            if ( ! $request ) {
                $this->error( "Request {$requestNumber} not found" );

                return 1;
            }

            return $this->processRequest( $request ) ? 0 : 1;
        }

        $requests = PortabilityRequest::pending()
            ->orderBy( 'created_at' )
            ->limit( $limit )
            ->get();

        if ( $requests->isEmpty() ) {
            $this->info( 'No pending portability requests to process' );

            return 0;
        }

        $this->info( "Processing {$requests->count()} portability request(s)..." );

        $processed = 0;
        $failed    = 0;

        foreach ( $requests as $request ) {
            if ( $this->processRequest( $request ) ) {
                $processed++;
            } else {
                $failed++;
            }
        }

        $this->newLine();
        $this->info( "Processed: {$processed}, Failed: {$failed}" );

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Process a single request.
     */
    protected function processRequest( PortabilityRequest $request ): bool
    {
        $this->line( "Processing {$request->request_number}..." );

        try {
            $result = $this->portabilityService->processRequest( $request );

            $this->info( "  [COMPLETED] {$request->request_number} - File: {$result->file_path}" );

            return true;
        } catch ( Exception $e ) {
            $this->error( "  [FAILED] {$request->request_number}: {$e->getMessage()}" );

            return false;
        }
    }

    /**
     * Cleanup expired exports.
     */
    protected function cleanupExpired(): int
    {
        $this->info( 'Cleaning up expired exports...' );

        $count = $this->portabilityService->cleanupExpired();

        $this->info( "Cleaned up {$count} expired export(s)" );

        return 0;
    }
}
