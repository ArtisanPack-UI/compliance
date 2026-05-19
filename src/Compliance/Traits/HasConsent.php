<?php

/**
 * HasConsent trait.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Traits;

use ArtisanPackUI\Compliance\Models\ConsentRecord;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait HasConsent
{
    /**
     * Get all consent records for this user.
     */
    public function consentRecords(): HasMany
    {
        return $this->hasMany( ConsentRecord::class, 'user_id' );
    }

    /**
     * Check if user has valid consent for a purpose.
     */
    public function hasConsentFor( string $purpose ): bool
    {
        return $this->consentRecords()
            ->valid()
            ->where( 'purpose', $purpose )
            ->exists();
    }

    /**
     * Get consent record for a specific purpose.
     */
    public function getConsentFor( string $purpose ): ?ConsentRecord
    {
        return $this->consentRecords()
            ->where( 'purpose', $purpose )
            ->latest()
            ->first();
    }

    /**
     * Get all valid consents.
     */
    public function getValidConsents(): Collection
    {
        return $this->consentRecords()
            ->valid()
            ->get();
    }

    /**
     * Get consent status for multiple purposes.
     *
     * @param  array<string>  $purposes
     *
     * @return array<string, bool>
     */
    public function getConsentStatus( array $purposes ): array
    {
        $consents = $this->consentRecords()
            ->valid()
            ->whereIn( 'purpose', $purposes )
            ->pluck( 'purpose' )
            ->toArray();

        $status = [];
        foreach ( $purposes as $purpose ) {
            $status[ $purpose ] = in_array( $purpose, $consents );
        }

        return $status;
    }

    /**
     * Check if user needs to reconsent for any purposes.
     *
     * @return array<string>
     */
    public function getPurposesRequiringReconsent(): array
    {
        $purposes = [];

        $consents = $this->consentRecords()
            ->where( 'status', 'granted' )
            ->with( 'policy' )
            ->get();

        foreach ( $consents as $consent ) {
            if ( $consent->policy && ! $consent->policy->isEffective() ) {
                $purposes[] = $consent->purpose;
            }
        }

        return array_unique( $purposes );
    }

    /**
     * Get consent history for user.
     */
    public function getConsentHistory( ?string $purpose = null ): Collection
    {
        $query = $this->consentRecords();

        if ( $purpose ) {
            $query->where( 'purpose', $purpose );
        }

        return $query->orderByDesc( 'created_at' )->get();
    }

    /**
     * Check if user has any withdrawn consents.
     */
    public function hasWithdrawnConsents(): bool
    {
        return $this->consentRecords()
            ->where( 'status', 'withdrawn' )
            ->exists();
    }

    /**
     * Get all purposes user has consented to (ever).
     *
     * @return array<string>
     */
    public function getAllConsentedPurposes(): array
    {
        return $this->consentRecords()
            ->where( 'status', 'granted' )
            ->distinct( 'purpose' )
            ->pluck( 'purpose' )
            ->toArray();
    }
}
