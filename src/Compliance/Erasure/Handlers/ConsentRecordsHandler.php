<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Erasure\Handlers;

use ArtisanPackUI\Compliance\Compliance\Erasure\ErasureHandlerResult;
use ArtisanPackUI\Compliance\Models\ConsentAuditLog;
use ArtisanPackUI\Compliance\Models\ConsentRecord;
use Exception;
use Illuminate\Support\Collection;

class ConsentRecordsHandler extends BaseErasureHandler
{
    protected array $dataCategories = ['consent', 'preferences'];

    protected bool $reversible = false;

    protected int $estimatedTime = 15;

    /**
     * Get handler name.
     */
    public function getName(): string
    {
        return 'consent_records';
    }

    /**
     * Get handler description.
     */
    public function getDescription(): string
    {
        return 'Handles consent records and audit log erasure';
    }

    /**
     * Check if handler can process for user.
     */
    public function canHandle( int $userId ): bool
    {
        // Cover users who only have audit-log entries (e.g. after an
        // earlier withdraw + soft-delete cycle) — otherwise erase()
        // would skip them and the audit logs would survive a "right
        // to be forgotten" request.
        return ConsentRecord::where( 'user_id', $userId )->exists()
            || ConsentAuditLog::where( 'user_id', $userId )->exists();
    }

    /**
     * Find all user consent data.
     */
    public function findUserData( int $userId ): Collection
    {
        return collect( [
            'consent_records' => ConsentRecord::where( 'user_id', $userId )->get(),
            'audit_logs'      => ConsentAuditLog::where( 'user_id', $userId )->get(),
        ] );
    }

    /**
     * Execute erasure.
     *
     * @param  array<string, mixed>  $options
     */
    public function erase( int $userId, array $options = [] ): ErasureHandlerResult
    {
        $this->logErasure( 'starting', ['user_id' => $userId] );

        try {
            // Wrap both deletes in a single transaction so a failure
            // halfway through can't leave consents deleted but audit
            // logs intact (or vice versa) with mismatched reported
            // counts.
            return \Illuminate\Support\Facades\DB::transaction( function () use ( $userId ) {
                $consentCount = ConsentRecord::where( 'user_id', $userId )->count();
                $auditCount   = ConsentAuditLog::where( 'user_id', $userId )->count();

                $totalFound = $consentCount + $auditCount;

                ConsentRecord::where( 'user_id', $userId )->delete();
                ConsentAuditLog::where( 'user_id', $userId )->delete();

                $this->logErasure( 'completed', [
                    'user_id'         => $userId,
                    'consent_records' => $consentCount,
                    'audit_logs'      => $auditCount,
                ] );

                return $this->success( $totalFound, $totalFound );
            } );
        } catch ( Exception $e ) {
            $this->logErasure( 'failed', ['user_id' => $userId, 'error' => $e->getMessage()] );

            return $this->failed( $e->getMessage() );
        }
    }
}
