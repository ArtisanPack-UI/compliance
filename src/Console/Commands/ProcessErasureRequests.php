<?php

/**
 * ProcessErasureRequests Artisan console command.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Console\Commands;

use ArtisanPackUI\Compliance\Compliance\Erasure\ErasureService;
use ArtisanPackUI\Compliance\Models\ErasureRequest;
use Exception;
use Illuminate\Console\Command;

class ProcessErasureRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:process-erasure
                            {--request= : Process a specific request}
                            {--limit=10 : Maximum requests to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending erasure requests';

    public function __construct( protected ErasureService $erasureService )
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $requestNumber = $this->option( 'request' );
        $limit         = (int) $this->option( 'limit' );

        if ( $requestNumber ) {
            $request = ErasureRequest::where( 'request_number', $requestNumber )->first();

            if ( ! $request ) {
                $this->error( "Request {$requestNumber} not found" );

                return 1;
            }

            return $this->processRequest( $request ) ? 0 : 1;
        }

        $requests = ErasureRequest::where( 'status', 'approved' )
            ->orderBy( 'created_at' )
            ->limit( $limit )
            ->get();

        if ( $requests->isEmpty() ) {
            $this->info( 'No pending erasure requests to process' );

            return 0;
        }

        $this->info( "Processing {$requests->count()} erasure request(s)..." );

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
    protected function processRequest( ErasureRequest $request ): bool
    {
        $this->line( "Processing {$request->request_number}..." );

        try {
            $result = $this->erasureService->processRequest( $request );

            if ( 'completed' === $result->status ) {
                $this->info( "  [COMPLETED] {$request->request_number}" );

                return true;
            } else {
                $this->warn( "  [PARTIAL] {$request->request_number} - Some handlers failed" );

                return true;
            }
        } catch ( Exception $e ) {
            $this->error( "  [FAILED] {$request->request_number}: {$e->getMessage()}" );

            return false;
        }
    }
}
