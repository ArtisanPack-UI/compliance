<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Minimization;

use ArtisanPackUI\Compliance\Compliance\Validation\ValidationResult;
use ArtisanPackUI\Compliance\Models\CollectionPolicy;
use DateTimeInterface;
use Illuminate\Support\Collection;

class DataMinimizerService
{
    protected AnonymizationEngine $anonymizer;

    protected PseudonymizationEngine $pseudonymizer;

    /**
     * @var array<string, CollectionPolicy>
     */
    protected array $policies = [];

    public function __construct(
        AnonymizationEngine $anonymizer,
        PseudonymizationEngine $pseudonymizer,
    ) {
        $this->anonymizer    = $anonymizer;
        $this->pseudonymizer = $pseudonymizer;
    }

    /**
     * Apply collection policy to incoming data.
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>
     */
    public function applyCollectionPolicy( array $data, string $purpose ): array
    {
        $policy = $this->getCollectionPolicy( $purpose );

        if ( ! $policy ) {
            return $data;
        }

        return $policy->filterData( $data );
    }

    /**
     * Check if data collection is compliant.
     *
     * @param  array<string, mixed>  $data
     */
    public function validateCollection( array $data, string $purpose ): ValidationResult
    {
        $policy = $this->getCollectionPolicy( $purpose );

        if ( ! $policy ) {
            return ValidationResult::valid();
        }

        $errors   = [];
        $warnings = [];

        // Check for missing required fields
        $missingRequired = $policy->getMissingRequiredFields( $data );
        foreach ( $missingRequired as $field ) {
            $errors[] = "Required field '{$field}' is missing for purpose '{$purpose}'";
        }

        // Check for prohibited fields
        $prohibited = $policy->prohibited_fields ?? [];
        foreach ( array_keys( $data ) as $field ) {
            if ( in_array( $field, $prohibited ) ) {
                $errors[] = "Field '{$field}' is prohibited for purpose '{$purpose}'";
            }
        }

        // Check for unnecessary fields (not in allowed)
        $allowed = $policy->allowed_fields ?? [];
        if ( ! empty( $allowed ) ) {
            foreach ( array_keys( $data ) as $field ) {
                if ( ! in_array( $field, $allowed ) && ! in_array( $field, $prohibited ) ) {
                    $warnings[] = "Field '{$field}' may be unnecessary for purpose '{$purpose}'";
                }
            }
        }

        return new ValidationResult(
            isValid: empty( $errors ),
            errors: $errors,
            warnings: $warnings,
        );
    }

    /**
     * Anonymize dataset.
     *
     * @param  array<string>  $fields
     */
    public function anonymize( Collection $data, array $fields ): Collection
    {
        return $data->map( function ( $item ) use ( $fields ) {
            $itemArray = is_array( $item ) ? $item : $item->toArray();

            foreach ( $fields as $field ) {
                if ( isset( $itemArray[ $field ] ) ) {
                    $itemArray[ $field ] = $this->anonymizer->anonymizeValue(
                        $itemArray[ $field ],
                        $this->detectType( $itemArray[ $field ] ),
                    );
                }
            }

            return $itemArray;
        } );
    }

    /**
     * Pseudonymize dataset.
     *
     * @param  array<string>  $fields
     */
    public function pseudonymize( Collection $data, array $fields ): PseudonymizedResult
    {
        $pseudonymizedData = $data->map( function ( $item ) use ( $fields ) {
            $itemArray = is_array( $item ) ? $item : $item->toArray();

            foreach ( $fields as $field ) {
                if ( isset( $itemArray[ $field ] ) ) {
                    $itemArray[ $field ] = $this->pseudonymizer->pseudonymize(
                        (string) $itemArray[ $field ],
                        $field,
                    );
                }
            }

            return $itemArray;
        } );

        return new PseudonymizedResult(
            data: $pseudonymizedData,
            mappingKey: $this->pseudonymizer->getCurrentMappingKey(),
        );
    }

    /**
     * Reverse pseudonymization (with authorization).
     */
    public function dePseudonymize( string $pseudonym, string $field ): ?string
    {
        return $this->pseudonymizer->dePseudonymize( $pseudonym, $field );
    }

    /**
     * Get data that has exceeded retention period.
     *
     * @param  class-string  $model
     */
    public function getExpiredData( string $model ): Collection
    {
        if ( ! method_exists( $model, 'scopeExpiredRetention' ) ) {
            return collect();
        }

        return $model::expiredRetention()->get();
    }

    /**
     * Purge expired data.
     *
     * @param  class-string  $model
     */
    public function purgeExpiredData( string $model ): int
    {
        if ( ! method_exists( $model, 'scopeExpiredRetention' ) ) {
            return 0;
        }

        $batchSize = config( 'artisanpack.compliance.compliance.minimization.purge_batch_size', 1000 );

        $count = 0;
        do {
            $deleted = $model::expiredRetention()
                ->limit( $batchSize )
                ->delete();
            $count += $deleted;
        } while ( $deleted > 0 );

        return $count;
    }

    /**
     * Get collection policy for purpose.
     */
    public function getCollectionPolicy( string $purpose ): ?CollectionPolicy
    {
        if ( isset( $this->policies[ $purpose ] ) ) {
            return $this->policies[ $purpose ];
        }

        $policy = CollectionPolicy::getForPurpose( $purpose );
        if ( $policy ) {
            $this->policies[ $purpose ] = $policy;
        }

        return $policy;
    }

    /**
     * Register a collection policy.
     */
    public function registerPolicy( string $purpose, CollectionPolicy $policy ): void
    {
        $this->policies[ $purpose ] = $policy;
    }

    /**
     * Detect the type of a value for appropriate anonymization.
     */
    protected function detectType( mixed $value ): string
    {
        if ( filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
            return 'email';
        }

        if ( is_string( $value ) && preg_match( '/^[\d\s\-\+\(\)]+$/', $value ) && strlen( $value ) >= 7 ) {
            return 'phone';
        }

        if ( is_numeric( $value ) ) {
            return 'number';
        }

        if ( $value instanceof DateTimeInterface ) {
            return 'date';
        }

        return 'string';
    }
}
