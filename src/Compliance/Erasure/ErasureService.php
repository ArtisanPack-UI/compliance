<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Erasure;

use ArtisanPackUI\Compliance\Compliance\Contracts\ErasureHandlerInterface;
use ArtisanPackUI\Compliance\Events\ErasureCompleted;
use ArtisanPackUI\Compliance\Events\ErasureRequested;
use ArtisanPackUI\Compliance\Models\ErasureLog;
use ArtisanPackUI\Compliance\Models\ErasureRequest;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;

class ErasureService
{
    /**
     * @var array<string, ErasureHandlerInterface>
     */
    protected array $handlers = [];

    /**
     * Register an erasure handler.
     */
    public function registerHandler( ErasureHandlerInterface $handler ): void
    {
        $this->handlers[ $handler->getName() ] = $handler;
    }

    /**
     * Get all registered handlers.
     *
     * @return array<string, ErasureHandlerInterface>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Create an erasure request.
     *
     * @param  array<string, mixed>  $options
     */
    public function createRequest( int $userId, array $options = [] ): ErasureRequest
    {
        $request = ErasureRequest::create( [
            'user_id'           => $userId,
            'requester_type'    => $options['requester_type'] ?? 'self',
            'requester_contact' => $options['requester_contact'] ?? null,
            'status'            => 'pending',
            'scope'             => $options['scope'] ?? 'full',
            'specific_data'     => $options['specific_data'] ?? null,
            'reason'            => $options['reason'] ?? null,
            'identity_verified' => $options['identity_verified'] ?? false,
            'created_by'        => auth()->id(),
        ] );

        event( new ErasureRequested( $request ) );

        return $request;
    }

    /**
     * Process an erasure request.
     */
    public function processRequest( ErasureRequest $request ): ErasureRequest
    {
        return DB::transaction( function () use ( $request ) {
            // Atomic claim: only an approved + identity-verified request
            // that hasn't already entered processing should advance.
            // The conditional UPDATE doubles as a lock — a second
            // caller racing on the same row sees zero affected rows.
            $updated = ErasureRequest::whereKey( $request->id )
                ->where( 'status', 'approved' )
                ->where( 'identity_verified', true )
                ->update( ['status' => 'processing'] );

            if ( 0 === $updated ) {
                throw new RuntimeException(
                    "Erasure request {$request->request_number} is not eligible for processing "
                        . '(must be approved and identity-verified, and not already in progress).',
                );
            }

            $request->refresh();

            $processedHandlers = [];
            $failedHandlers    = [];
            $exemptions        = [];

            foreach ( $this->handlers as $name => $handler ) {
                // Skip if partial scope and handler not in specific data
                if ( 'partial' === $request->scope && $request->specific_data ) {
                    if ( ! in_array( $name, $request->specific_data ) ) {
                        continue;
                    }
                }

                if ( ! $handler->canHandle( $request->user_id ) ) {
                    continue;
                }

                $log = $this->createLog( $request, $handler->getName(), 'erase' );

                try {
                    $result = $handler->erase( $request->user_id, [
                        'scope'         => $request->scope,
                        'specific_data' => $request->specific_data,
                    ] );

                    $log->update( [
                        'status'           => $result->success ? 'success' : 'failed',
                        'records_found'    => $result->recordsFound,
                        'records_erased'   => $result->recordsErased,
                        'records_retained' => $result->recordsRetained,
                        'retention_reason' => $result->retentionReason,
                        'backup_reference' => $this->encodeBackupReference( $result->backupData ),
                        'error_message'    => $result->error,
                        'completed_at'     => now(),
                    ] );

                    if ( $result->success ) {
                        $processedHandlers[] = $name;
                    } else {
                        $failedHandlers[] = $name;
                    }

                    if ( $result->recordsRetained > 0 ) {
                        $exemptions[] = [
                            'handler'  => $name,
                            'retained' => $result->recordsRetained,
                            'reason'   => $result->retentionReason,
                        ];
                    }
                } catch ( Exception $e ) {
                    Log::error( "Erasure handler {$name} failed", [
                        'request_id' => $request->id,
                        'user_id'    => $request->user_id,
                        'error'      => $e->getMessage(),
                    ] );

                    $log->update( [
                        'status'        => 'failed',
                        'error_message' => $e->getMessage(),
                        'completed_at'  => now(),
                    ] );

                    $failedHandlers[] = $name;
                }
            }

            $status = empty( $failedHandlers ) ? 'completed' : 'failed';
            $request->update( [
                'status'                => $status,
                'handlers_processed'    => $processedHandlers,
                'handlers_failed'       => $failedHandlers,
                'exemptions_found'      => $exemptions,
                'exemption_explanation' => ! empty( $exemptions )
                    ? 'Some data was retained due to legal or contractual obligations'
                    : null,
                'completed_at' => now(),
            ] );

            // Generate certificate if configured
            if ( config( 'artisanpack.compliance.compliance.erasure.generate_certificate', true ) ) {
                $this->generateCertificate( $request );
            }

            event( new ErasureCompleted( $request ) );

            return $request->fresh();
        } );
    }

    /**
     * Verify identity for erasure request.
     */
    public function verifyIdentity( ErasureRequest $request, string $method ): bool
    {
        $request->update( [
            'identity_verified'        => true,
            'identity_verified_at'     => now(),
            'identity_verified_method' => $method,
            'status'                   => 'approved',
        ] );

        return true;
    }

    /**
     * Reject an erasure request.
     */
    public function rejectRequest( ErasureRequest $request, string $reason ): ErasureRequest
    {
        $request->update( [
            'status'           => 'rejected',
            'rejected_at'      => now(),
            'rejected_by'      => auth()->id(),
            'rejection_reason' => $reason,
        ] );

        return $request;
    }

    /**
     * Get user data preview before erasure.
     */
    public function previewUserData( int $userId ): Collection
    {
        $preview = collect();

        foreach ( $this->handlers as $name => $handler ) {
            if ( ! $handler->canHandle( $userId ) ) {
                continue;
            }

            $data = $handler->findUserData( $userId );
            $preview->put( $name, [
                'handler'        => $name,
                'description'    => $handler->getDescription(),
                'categories'     => $handler->getDataCategories(),
                'record_count'   => $data->count(),
                'estimated_time' => $handler->getEstimatedTime(),
                'reversible'     => $handler->isReversible(),
            ] );
        }

        return $preview;
    }

    /**
     * Get pending requests for a user.
     */
    public function getPendingRequests( int $userId ): Collection
    {
        return ErasureRequest::where( 'user_id', $userId )
            ->pending()
            ->get();
    }

    /**
     * Get all overdue requests.
     */
    public function getOverdueRequests(): Collection
    {
        return ErasureRequest::overdue()->get();
    }

    /**
     * Notify third parties about erasure.
     *
     * @param  array<string>  $thirdParties
     */
    public function notifyThirdParties( ErasureRequest $request, array $thirdParties ): void
    {
        $notified = [];

        foreach ( $thirdParties as $party ) {
            // TODO: Implement actual notification logic
            $notified[] = [
                'party'       => $party,
                'notified_at' => now()->toIso8601String(),
                'method'      => 'email',
            ];
        }

        $request->update( [
            'third_parties_notified' => $notified,
        ] );
    }

    /**
     * Generate erasure certificate.
     */
    public function generateCertificate( ErasureRequest $request ): string
    {
        $certificate = [
            'certificate_id'     => 'EC-' . $request->request_number,
            'request_number'     => $request->request_number,
            'user_id'            => $request->user_id,
            'scope'              => $request->scope,
            'completed_at'       => $request->completed_at?->toIso8601String(),
            'handlers_processed' => $request->handlers_processed,
            'exemptions'         => $request->exemptions_found,
            'generated_at'       => now()->toIso8601String(),
            'organization'       => config( 'app.name' ),
        ];

        // JSON_THROW_ON_ERROR so a malformed certificate payload fails
        // loudly here rather than writing a `false` body and pretending
        // to have generated a valid certificate.
        $content = json_encode( $certificate, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT );
        $path    = 'erasure-certificates/' . $request->request_number . '.json';

        Storage::put( $path, $content );

        $request->update( ['certificate_path' => $path] );

        return $path;
    }

    /**
     * Rollback an erasure if possible.
     */
    public function rollback( ErasureRequest $request ): bool
    {
        $logs            = $request->logs()->where( 'status', 'success' )->get();
        $rollbackSuccess = true;

        foreach ( $logs as $log ) {
            $handler = $this->handlers[ $log->handler_name ] ?? null;

            if ( ! $handler || ! $handler->isReversible() ) {
                continue;
            }

            // Coerce malformed backups to an empty array so rollback()
            // never receives null when json_decode silently fails.
            $backupData = $log->backup_reference
                ? ( json_decode( $log->backup_reference, true ) ?? [] )
                : [];

            try {
                $handler->rollback( $request->user_id, $backupData );

                ErasureLog::create( [
                    'request_id'   => $request->id,
                    'handler_name' => $log->handler_name,
                    'action'       => 'rollback',
                    'status'       => 'success',
                    'started_at'   => now(),
                    'completed_at' => now(),
                ] );
            } catch ( Exception $e ) {
                $rollbackSuccess = false;

                ErasureLog::create( [
                    'request_id'    => $request->id,
                    'handler_name'  => $log->handler_name,
                    'action'        => 'rollback',
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                    'started_at'    => now(),
                    'completed_at'  => now(),
                ] );
            }
        }

        return $rollbackSuccess;
    }

    /**
     * Create erasure log entry.
     */
    protected function createLog( ErasureRequest $request, string $handler, string $action ): ErasureLog
    {
        // Start as 'in_progress' — the row is updated to 'success' or
        // 'failed' once the handler completes. Defaulting to 'success'
        // here would mis-report any subsequent update() failure (e.g.
        // database connectivity blip) as a successful erasure.
        return ErasureLog::create( [
            'request_id'   => $request->id,
            'handler_name' => $handler,
            'action'       => $action,
            'status'       => 'in_progress',
            'started_at'   => now(),
        ] );
    }

    /**
     * Encode a handler's backup payload for storage on the log row.
     *
     * Returns null on empty input or encode failure rather than letting
     * `json_encode`'s `false` return get cast to a string and stored.
     */
    protected function encodeBackupReference( ?array $backupData ): ?string
    {
        if ( empty( $backupData ) ) {
            return null;
        }

        try {
            return json_encode( $backupData, JSON_THROW_ON_ERROR );
        } catch ( JsonException $e ) {
            Log::warning( 'ErasureService: failed to encode backup_reference', [
                'message' => $e->getMessage(),
            ] );

            return null;
        }
    }
}
