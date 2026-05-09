<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Consent;

use ArtisanPackUI\Compliance\Events\ConsentGranted;
use ArtisanPackUI\Compliance\Events\ConsentWithdrawn;
use ArtisanPackUI\Compliance\Models\ConsentAuditLog;
use ArtisanPackUI\Compliance\Models\ConsentPolicy;
use ArtisanPackUI\Compliance\Models\ConsentRecord;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConsentManager
{
    /**
     * Record a consent grant.
     *
     * @param  array<string, mixed>  $options
     */
    public function grant( int $userId, string $purpose, array $options = [] ): ConsentRecord
    {
        $policy = ConsentPolicy::getLatestForPurpose( $purpose );

        if ( ! $policy ) {
            throw new InvalidArgumentException( "No active consent policy found for purpose: {$purpose}" );
        }

        return DB::transaction( function () use ( $userId, $purpose, $policy, $options ) {
            // Check for existing consent and withdraw it (use pessimistic locking to prevent race conditions)
            $existing = ConsentRecord::where( 'user_id', $userId )
                ->where( 'purpose', $purpose )
                ->where( 'status', 'granted' )
                ->lockForUpdate()
                ->first();

            if ( $existing ) {
                $existing->update( [
                    'status'            => 'withdrawn',
                    'withdrawn_at'      => now(),
                    'withdrawal_reason' => 'Superseded by new consent',
                ] );
            }

            // Create new consent record
            $record = ConsentRecord::create( [
                'user_id'            => $userId,
                'purpose'            => $purpose,
                'policy_id'          => $policy->id,
                'policy_version'     => $policy->version,
                'status'             => 'granted',
                'consent_type'       => $options['consent_type'] ?? ( $policy->requires_explicit ? 'explicit' : 'implied' ),
                'collection_method'  => $options['collection_method'] ?? 'web_form',
                'collection_context' => $options['collection_context'] ?? null,
                'ip_address'         => $options['ip_address'] ?? request()->ip(),
                'user_agent'         => $options['user_agent'] ?? request()->userAgent(),
                'proof_reference'    => $options['proof_reference'] ?? null,
                'granular_choices'   => $options['granular_choices'] ?? null,
                'expires_at'         => $this->calculateExpiration( $policy ),
                'metadata'           => $options['metadata'] ?? null,
            ] );

            // Log the consent grant
            $this->logConsentChange( $record, 'granted', null, 'granted' );

            // Defer event dispatch until the transaction commits — listeners
            // doing external work (sending emails, calling out to a
            // marketing tool, etc.) shouldn't observe uncommitted state
            // and shouldn't fire if a later operation rolls the transaction
            // back.
            DB::afterCommit( fn () => event( new ConsentGranted( $record ) ) );

            return $record;
        } );
    }

    /**
     * Withdraw consent.
     */
    public function withdraw( int $userId, string $purpose, ?string $reason = null ): bool
    {
        return DB::transaction( function () use ( $userId, $purpose, $reason ) {
            // Pessimistic lock to mirror grant()'s locking. Without this
            // a concurrent grant() could land between our SELECT and the
            // UPDATE, leaving the new grant in place while we mark the
            // stale row as withdrawn.
            $record = ConsentRecord::where( 'user_id', $userId )
                ->where( 'purpose', $purpose )
                ->where( 'status', 'granted' )
                ->lockForUpdate()
                ->first();

            if ( ! $record ) {
                return false;
            }

            $record->update( [
                'status'            => 'withdrawn',
                'withdrawn_at'      => now(),
                'withdrawal_reason' => $reason,
            ] );

            // Log the withdrawal
            $this->logConsentChange( $record, 'withdrawn', 'granted', 'withdrawn', $reason );

            // Defer event dispatch until commit (see grant() for rationale).
            DB::afterCommit( fn () => event( new ConsentWithdrawn( $record ) ) );

            return true;
        } );
    }

    /**
     * Check if consent is valid for purpose.
     */
    public function hasConsent( int $userId, string $purpose ): bool
    {
        return ConsentRecord::where( 'user_id', $userId )
            ->where( 'purpose', $purpose )
            ->valid()
            ->exists();
    }

    /**
     * Get all consents for user.
     */
    public function getConsents( int $userId ): Collection
    {
        return ConsentRecord::where( 'user_id', $userId )
            ->with( 'policy' )
            ->orderByDesc( 'created_at' )
            ->get();
    }

    /**
     * Get consent status for multiple purposes.
     *
     * @param  array<string>  $purposes
     *
     * @return array<string, bool>
     */
    public function getConsentStatus( int $userId, array $purposes ): array
    {
        $granted = ConsentRecord::where( 'user_id', $userId )
            ->whereIn( 'purpose', $purposes )
            ->valid()
            ->pluck( 'purpose' )
            ->toArray();

        $status = [];
        foreach ( $purposes as $purpose ) {
            $status[ $purpose ] = in_array( $purpose, $granted );
        }

        return $status;
    }

    /**
     * Get consent history for user.
     */
    public function getHistory( int $userId, ?string $purpose = null ): Collection
    {
        $query = ConsentAuditLog::where( 'user_id', $userId );

        if ( $purpose ) {
            $query->where( 'purpose', $purpose );
        }

        return $query->orderByDesc( 'created_at' )->get();
    }

    /**
     * Update consent policy version.
     *
     * @param  array<string, mixed>  $changes
     *
     * @throws InvalidArgumentException When creating first policy without required fields
     */
    public function updatePolicyVersion( string $purpose, string $version, array $changes ): ConsentPolicy
    {
        return DB::transaction( function () use ( $purpose, $version, $changes ) {
            // Lock the active policy row for this purpose so two concurrent
            // updatePolicyVersion calls can't both insert a new active row
            // (each thinking the other's read returned null or stale data).
            $currentPolicy = ConsentPolicy::where( 'purpose', $purpose )
                ->where( 'is_active', true )
                ->lockForUpdate()
                ->first();

            // Validate required fields when creating the first policy.
            // The contract says BOTH `name` AND `legal_text` are required —
            // an `&&` here was wrong (it accepts the policy when only one
            // field is present).
            if ( null === $currentPolicy ) {
                if ( empty( $changes['name'] ) || empty( $changes['legal_text'] ) ) {
                    throw new InvalidArgumentException(
                        'When creating the first policy for a purpose, "name" and "legal_text" are required in the changes array.',
                    );
                }
            }

            $newPolicy = ConsentPolicy::create( [
                'purpose'                 => $purpose,
                'name'                    => $changes['name'] ?? $currentPolicy?->name ?? ucfirst( $purpose ),
                'description'             => $changes['description'] ?? $currentPolicy?->description,
                'legal_text'              => $changes['legal_text'] ?? $currentPolicy?->legal_text ?? '',
                'version'                 => $version,
                'previous_version_id'     => $currentPolicy?->id ?? null,
                'data_categories'         => $changes['data_categories'] ?? $currentPolicy?->data_categories,
                'processing_details'      => $changes['processing_details'] ?? $currentPolicy?->processing_details,
                'retention_period'        => $changes['retention_period'] ?? $currentPolicy?->retention_period,
                'third_party_sharing'     => $changes['third_party_sharing'] ?? $currentPolicy?->third_party_sharing,
                'rights_description'      => $changes['rights_description'] ?? $currentPolicy?->rights_description,
                'withdrawal_consequences' => $changes['withdrawal_consequences'] ?? $currentPolicy?->withdrawal_consequences,
                'is_required'             => $changes['is_required'] ?? $currentPolicy?->is_required ?? false,
                'is_active'               => true,
                'requires_explicit'       => $changes['requires_explicit'] ?? $currentPolicy?->requires_explicit ?? true,
                'minimum_age'             => $changes['minimum_age'] ?? $currentPolicy?->minimum_age ?? 16,
                'effective_at'            => $changes['effective_at'] ?? now(),
                'expires_at'              => $changes['expires_at'] ?? null,
                'changes_from_previous'   => $changes,
                'created_by'              => auth()->id(),
            ] );

            // Deactivate old policy
            if ( $currentPolicy ) {
                $currentPolicy->update( ['is_active' => false] );
            }

            return $newPolicy;
        } );
    }

    /**
     * Get users who need to reconsent.
     */
    public function getUsersRequiringReconsent( string $purpose ): Collection
    {
        $currentPolicy = ConsentPolicy::getLatestForPurpose( $purpose );

        if ( ! $currentPolicy ) {
            return collect();
        }

        return ConsentRecord::where( 'purpose', $purpose )
            ->where( 'status', 'granted' )
            ->where( 'policy_version', '!=', $currentPolicy->version )
            ->get();
    }

    /**
     * Send reconsent notifications.
     */
    public function notifyReconsent( string $purpose ): int
    {
        $records = $this->getUsersRequiringReconsent( $purpose );
        $count   = 0;

        foreach ( $records as $record ) {
            // TODO: Send notification to user
            $count++;
        }

        return $count;
    }

    /**
     * Export consent records for user.
     *
     * @return array<string, mixed>
     */
    public function exportConsents( int $userId ): array
    {
        $records = $this->getConsents( $userId );

        return $records->map( function ( ConsentRecord $record ) {
            return [
                'purpose'          => $record->purpose,
                'policy_version'   => $record->policy_version,
                'status'           => $record->status,
                'consent_type'     => $record->consent_type,
                'granted_at'       => $record->created_at->toIso8601String(),
                'withdrawn_at'     => $record->withdrawn_at?->toIso8601String(),
                'expires_at'       => $record->expires_at?->toIso8601String(),
                'granular_choices' => $record->granular_choices,
            ];
        } )->toArray();
    }

    /**
     * Verify consent is valid (not expired, policy not changed).
     */
    public function verifyConsent( int $userId, string $purpose ): ConsentVerification
    {
        $record = ConsentRecord::where( 'user_id', $userId )
            ->where( 'purpose', $purpose )
            ->where( 'status', 'granted' )
            ->first();

        if ( ! $record ) {
            return ConsentVerification::invalid( 'No consent record found' );
        }

        if ( $record->isExpired() ) {
            return ConsentVerification::invalid( 'Consent has expired', true );
        }

        // Check if policy has changed
        $currentPolicy = ConsentPolicy::getLatestForPurpose( $purpose );
        if ( $currentPolicy && $record->policy_version !== $currentPolicy->version ) {
            if ( config( 'artisanpack.compliance.consent.reconsent_on_policy_change', true ) ) {
                return ConsentVerification::reconsentRequired( $record, 'Policy has been updated' );
            }
        }

        return ConsentVerification::valid( $record );
    }

    /**
     * Get consent statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics( ?string $purpose = null ): array
    {
        $query = ConsentRecord::query();

        if ( $purpose ) {
            $query->where( 'purpose', $purpose );
        }

        $total     = $query->count();
        $granted   = ( clone $query )->where( 'status', 'granted' )->count();
        $withdrawn = ( clone $query )->where( 'status', 'withdrawn' )->count();
        $expired   = ( clone $query )->where( 'status', 'expired' )->count();

        $byPurposeQuery = ConsentRecord::selectRaw( 'purpose, status, count(*) as count' );

        if ( $purpose ) {
            $byPurposeQuery->where( 'purpose', $purpose );
        }

        $byPurpose = $byPurposeQuery
            ->groupBy( 'purpose', 'status' )
            ->get()
            ->groupBy( 'purpose' )
            ->map( function ( $items ) {
                return $items->pluck( 'count', 'status' );
            } );

        return [
            'total'      => $total,
            'granted'    => $granted,
            'withdrawn'  => $withdrawn,
            'expired'    => $expired,
            'grant_rate' => $total > 0 ? round( ( $granted / $total ) * 100, 2 ) : 0,
            'by_purpose' => $byPurpose,
        ];
    }

    /**
     * Calculate consent expiration date.
     */
    protected function calculateExpiration( ConsentPolicy $policy ): ?DateTime
    {
        // Prefer the policy's own retention_period (per-purpose
        // expiration); fall back to the global default. A null on both
        // sides means consent never expires.
        $expiryDays = $policy->retention_period
            ?? config( 'artisanpack.compliance.consent.default_expiry_days' );

        if ( null === $expiryDays ) {
            return null;
        }

        return now()->addDays( (int) $expiryDays );
    }

    /**
     * Log a consent change.
     */
    protected function logConsentChange(
        ConsentRecord $record,
        string $action,
        ?string $oldStatus,
        ?string $newStatus,
        ?string $reason = null,
    ): void {
        ConsentAuditLog::create( [
            'consent_record_id' => $record->id,
            'user_id'           => $record->user_id,
            'action'            => $action,
            'purpose'           => $record->purpose,
            'old_status'        => $oldStatus,
            'new_status'        => $newStatus,
            'policy_version'    => $record->policy_version,
            'actor_type'        => auth()->check() ? 'user' : 'system',
            'actor_id'          => auth()->id(),
            'reason'            => $reason,
            'ip_address'        => request()->ip(),
            'user_agent'        => request()->userAgent(),
        ]);
    }
}
