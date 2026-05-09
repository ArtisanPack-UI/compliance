<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Consent;

use ArtisanPackUI\Compliance\Models\ConsentPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConsentPolicyService
{
    /**
     * Internal keys that should not be stored in changes_from_previous.
     *
     * @var array<string>
     */
    protected array $internalChangeKeys = [
        'version',
        'previous_version_id',
        'is_active',
        'created_by',
        'changes_from_previous',
    ];

    /**
     * Create a new consent policy.
     *
     * @param  array<string, mixed>  $data
     */
    public function create( array $data ): ConsentPolicy
    {
        // Get creator ID - allow null for system/unauthenticated contexts
        // The created_by column should be nullable in the database
        $creatorId = $data['created_by'] ?? ( auth()->check() ? auth()->id() : null );

        return ConsentPolicy::create( [
            'purpose'                 => $data['purpose'],
            'name'                    => $data['name'],
            'description'             => $data['description'] ?? null,
            'legal_text'              => $data['legal_text'],
            'version'                 => $data['version'] ?? '1.0',
            'previous_version_id'     => $data['previous_version_id'] ?? null,
            'data_categories'         => $data['data_categories'] ?? [],
            'processing_details'      => $data['processing_details'] ?? [],
            'retention_period'        => $data['retention_period'] ?? null,
            'third_party_sharing'     => $data['third_party_sharing'] ?? [],
            'rights_description'      => $data['rights_description'] ?? null,
            'withdrawal_consequences' => $data['withdrawal_consequences'] ?? null,
            'is_required'             => $data['is_required'] ?? false,
            'is_active'               => $data['is_active'] ?? true,
            'requires_explicit'       => $data['requires_explicit'] ?? true,
            'minimum_age'             => $data['minimum_age'] ?? 16,
            'effective_at'            => $data['effective_at'] ?? now(),
            'expires_at'              => $data['expires_at'] ?? null,
            'changes_from_previous'   => $data['changes_from_previous'] ?? null,
            'created_by'              => $creatorId,
        ] );
    }

    /**
     * Update policy (creates new version).
     *
     * @param  array<string, mixed>  $changes
     */
    public function update( ConsentPolicy $policy, array $changes ): ConsentPolicy
    {
        return DB::transaction( function () use ( $policy, $changes ) {
            // Determine new version with robust parsing
            $newVersion = $this->incrementVersion( $changes['version'] ?? $policy->version );

            // Filter internal keys from changes for audit trail
            $auditableChanges = $this->filterInternalKeys( $changes );

            // Deactivate old policy
            $policy->update( ['is_active' => false] );

            // Create new version
            return $this->create( array_merge( [
                'purpose'                 => $policy->purpose,
                'name'                    => $policy->name,
                'description'             => $policy->description,
                'legal_text'              => $policy->legal_text,
                'data_categories'         => $policy->data_categories,
                'processing_details'      => $policy->processing_details,
                'retention_period'        => $policy->retention_period,
                'third_party_sharing'     => $policy->third_party_sharing,
                'rights_description'      => $policy->rights_description,
                'withdrawal_consequences' => $policy->withdrawal_consequences,
                'is_required'             => $policy->is_required,
                'requires_explicit'       => $policy->requires_explicit,
                'minimum_age'             => $policy->minimum_age,
            ], $changes, [
                'version'               => $changes['version'] ?? $newVersion,
                'previous_version_id'   => $policy->id,
                'changes_from_previous' => $auditableChanges,
                'is_active'             => true,
            ] ) );
        } );
    }

    /**
     * Get active policy for purpose.
     */
    public function getActive( string $purpose ): ?ConsentPolicy
    {
        return ConsentPolicy::getLatestForPurpose( $purpose );
    }

    /**
     * Get all versions of a policy, sorted by semantic version (descending).
     */
    public function getVersions( string $purpose ): Collection
    {
        return ConsentPolicy::where( 'purpose', $purpose )
            ->get()
            ->sort( function ( ConsentPolicy $a, ConsentPolicy $b ) {
                // Use version_compare for proper semantic versioning
                return version_compare( $b->version, $a->version );
            } )
            ->values();
    }

    /**
     * Compare two policy versions.
     *
     * @return array<string, mixed>
     */
    public function compare( ConsentPolicy $v1, ConsentPolicy $v2 ): array
    {
        $fields = [
            'legal_text',
            'data_categories',
            'processing_details',
            'retention_period',
            'third_party_sharing',
            'rights_description',
            'withdrawal_consequences',
            'is_required',
            'requires_explicit',
            'minimum_age',
        ];

        $differences = [];

        foreach ( $fields as $field ) {
            if ( $v1->$field !== $v2->$field ) {
                $differences[ $field ] = [
                    'old' => $v1->$field,
                    'new' => $v2->$field,
                ];
            }
        }

        return [
            'version_1'          => $v1->version,
            'version_2'          => $v2->version,
            'differences'        => $differences,
            'requires_reconsent' => $this->requiresReconsent( $v1, $v2 ),
        ];
    }

    /**
     * Check if policy change requires reconsent.
     */
    public function requiresReconsent( ConsentPolicy $oldPolicy, ConsentPolicy $newPolicy ): bool
    {
        // Material changes that require reconsent
        $materialFields = [
            'data_categories',
            'processing_details',
            'third_party_sharing',
            'retention_period',
        ];

        foreach ( $materialFields as $field ) {
            if ( $oldPolicy->$field !== $newPolicy->$field ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deactivate a policy.
     */
    public function deactivate( ConsentPolicy $policy ): void
    {
        $policy->update( ['is_active' => false] );
    }

    /**
     * Get policies requiring user consent.
     */
    public function getRequiredPolicies(): Collection
    {
        return ConsentPolicy::active()
            ->effective()
            ->where( 'is_required', true )
            ->get();
    }

    /**
     * Get all active policies.
     */
    public function getAllActive(): Collection
    {
        return ConsentPolicy::active()->effective()->get();
    }

    /**
     * Get policies by purpose.
     *
     * @param  array<string>  $purposes
     */
    public function getByPurposes( array $purposes ): Collection
    {
        return ConsentPolicy::active()
            ->effective()
            ->whereIn( 'purpose', $purposes )
            ->get()
            ->keyBy( 'purpose' );
    }

    /**
     * Parse and increment a version string.
     *
     * Handles formats like "1.0", "1", "v1.2", "1.2.3" (ignores patch).
     *
     * @throws InvalidArgumentException If version cannot be parsed
     */
    protected function incrementVersion( string $version ): string
    {
        // Normalize: trim whitespace and strip leading 'v' or 'V'
        $normalized = ltrim( trim( $version ), 'vV' );

        // Handle empty string after normalization
        if ( '' === $normalized ) {
            return '1.0';
        }

        // Split by dots
        $parts = explode( '.', $normalized );

        // Extract and validate major version
        $major = $parts[0];
        if ( ! is_numeric( $major ) ) {
            throw new InvalidArgumentException(
                "Cannot parse version '{$version}': major version must be numeric.",
            );
        }

        // Extract minor version (default to 0 if missing or non-numeric)
        $minor = 0;
        if ( isset( $parts[1] ) && is_numeric( $parts[1] ) ) {
            $minor = (int) $parts[1];
        }

        // Increment minor version
        $minor++;

        return $major . '.' . $minor;
    }

    /**
     * Filter internal/metadata keys from changes array.
     *
     * @param  array<string, mixed>  $changes
     *
     * @return array<string, mixed>
     */
    protected function filterInternalKeys( array $changes ): array
    {
        return array_diff_key( $changes, array_flip( $this->internalChangeKeys));
    }
}
