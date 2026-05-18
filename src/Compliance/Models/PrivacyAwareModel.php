<?php

/**
 * Base model for privacy-aware Eloquent models — adds standard timestamps and privacy-related casts.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Models;

use ArtisanPackUI\Compliance\Compliance\Traits\Auditable;
use ArtisanPackUI\Compliance\Compliance\Traits\PrivacyByDesign;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class PrivacyAwareModel extends Model
{
    use Auditable;
    use PrivacyByDesign;

    /**
     * Automatically log all data access.
     */
    protected static bool $logDataAccess = true;

    /**
     * Automatically encrypt sensitive fields.
     */
    protected static bool $autoEncryptSensitive = true;

    /**
     * Retention period in days (null = indefinite).
     * Declared as static so scopes can access it properly.
     */
    protected static ?int $retentionDays = null;

    /**
     * Get lawful basis for processing.
     */
    public function getLawfulBasis(): string
    {
        return $this->lawfulBasis ?? 'consent';
    }

    /**
     * Check if retention period has expired.
     */
    public function isRetentionExpired(): bool
    {
        if ( null === static::$retentionDays ) {
            return false;
        }

        // copy() before arithmetic so we don't mutate the model's
        // created_at attribute and accidentally dirty it for save().
        return $this->created_at->copy()->addDays( static::$retentionDays )->isPast();
    }

    /**
     * Get time until retention expires.
     */
    public function getRetentionRemaining(): ?CarbonInterval
    {
        if ( null === static::$retentionDays ) {
            return null;
        }

        $expiresAt = $this->created_at->copy()->addDays( static::$retentionDays );

        if ( $expiresAt->isPast() ) {
            return CarbonInterval::days( 0 );
        }

        return $expiresAt->diffAsCarbonInterval( now() );
    }

    /**
     * Scope for data past retention period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @param  int|null  $days  Optional explicit retention days to use
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeExpiredRetention( $query, ?int $days = null )
    {
        $retentionDays = $days ?? static::$retentionDays;

        if ( null === $retentionDays ) {
            return $query->whereRaw( '1 = 0' ); // Return empty
        }

        return $query->where( 'created_at', '<', now()->subDays( $retentionDays ) );
    }

    /**
     * Scope for data within retention period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @param  int|null  $days  Optional explicit retention days to use
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithinRetention( $query, ?int $days = null )
    {
        $retentionDays = $days ?? static::$retentionDays;

        if ( null === $retentionDays ) {
            return $query; // All records are within retention
        }

        return $query->where( 'created_at', '>=', now()->subDays( $retentionDays ) );
    }

    /**
     * Get the audit trail for this record.
     */
    public function getAuditTrail(): Collection
    {
        // This would typically query an audit log table
        // For now, returning empty collection
        return collect();
    }

    /**
     * Export all data for this record (for portability).
     *
     * @return array<string, mixed>
     */
    public function exportData(): array
    {
        $data = $this->toArray();

        // Decrypt sensitive data for export
        foreach ( $this->getSensitiveDataAttributes() as $attribute ) {
            if ( isset( $data[ $attribute ] ) ) {
                $decrypted = $this->decryptSensitiveAttribute( $attribute );
                if ( null !== $decrypted ) {
                    $data[ $attribute ] = $decrypted;
                }
            }
        }

        return $data;
    }

    /**
     * Prepare data for deletion (for right to erasure).
     *
     * @return array<string, mixed>
     */
    public function prepareForDeletion(): array
    {
        return [
            'model'          => static::class,
            'id'             => $this->getKey(),
            'personal_data'  => $this->getPersonalData(),
            'sensitive_data' => array_keys( $this->getSensitiveData() ),
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Get the data category for this model.
     */
    public function getDataCategory(): string
    {
        return $this->dataCategory ?? 'general';
    }

    /**
     * Get the data subject type (e.g., 'customer', 'employee').
     */
    public function getDataSubjectType(): string
    {
        return $this->dataSubjectType ?? 'user';
    }

    /**
     * Boot the PrivacyAwareModel.
     */
    protected static function bootPrivacyAwareModel(): void
    {
        static::creating( function ( PrivacyAwareModel $model ): void {
            if ( static::$autoEncryptSensitive ) {
                $model->encryptSensitiveData();
            }
        } );

        static::updating( function ( PrivacyAwareModel $model ): void {
            if ( static::$autoEncryptSensitive ) {
                // Only encrypt changed sensitive attributes
                foreach ( $model->getSensitiveDataAttributes() as $attribute ) {
                    if ( $model->isDirty( $attribute ) ) {
                        $model->encryptSensitiveData();
                        break;
                    }
                }
            }
        } );

        static::retrieved( function ( PrivacyAwareModel $model ): void {
            if ( static::$logDataAccess ) {
                $accessor = auth()->id() ?? 'system';
                $model->logDataAccess( (string) $accessor, 'retrieval' );
            }
        } );
    }
}
