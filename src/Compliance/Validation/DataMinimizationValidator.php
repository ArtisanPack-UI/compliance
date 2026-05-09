<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Validation;

use ArtisanPackUI\Compliance\Models\CollectionPolicy;

class DataMinimizationValidator
{
    /**
     * Validate that only necessary data is collected.
     *
     * @param  array<string, mixed>  $data
     */
    public function validateCollection( array $data, string $purpose ): ValidationResult
    {
        $policy = CollectionPolicy::getForPurpose( $purpose );

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

        // Check for unnecessary fields
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
     * Get fields allowed for purpose.
     *
     * @return array<string>
     */
    public function getAllowedFields( string $purpose ): array
    {
        $policy = CollectionPolicy::getForPurpose( $purpose );

        return $policy?->allowed_fields ?? [];
    }

    /**
     * Get fields required for purpose.
     *
     * @return array<string>
     */
    public function getRequiredFields( string $purpose ): array
    {
        $policy = CollectionPolicy::getForPurpose( $purpose );

        return $policy?->required_fields ?? [];
    }

    /**
     * Check if field is necessary for purpose.
     */
    public function isNecessary( string $field, string $purpose ): bool
    {
        $policy = CollectionPolicy::getForPurpose( $purpose );

        if ( ! $policy ) {
            return true; // No policy means all fields allowed
        }

        // Check if prohibited
        if ( in_array( $field, $policy->prohibited_fields ?? [] ) ) {
            return false;
        }

        // If allowed list is empty, all non-prohibited are necessary
        if ( empty( $policy->allowed_fields ) ) {
            return true;
        }

        // Check if in allowed list
        return in_array( $field, $policy->allowed_fields );
    }

    /**
     * Validate data against minimization rules.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     *
     * @return array<string>
     */
    public function validate( array $data, array $rules ): array
    {
        $errors = [];

        foreach ( $rules as $field => $rule ) {
            if ( ! isset( $data[ $field ] ) ) {
                if ( $rule['required'] ?? false ) {
                    $errors[] = "Required field '{$field}' is missing";
                }

                continue;
            }

            $value = $data[ $field ];

            // Check max length
            if ( isset( $rule['max_length'] ) && is_string( $value ) && strlen( $value ) > $rule['max_length'] ) {
                $errors[] = "Field '{$field}' exceeds maximum length of {$rule['max_length']}";
            }

            // Check allowed values
            if ( isset( $rule['allowed_values'] ) && ! in_array( $value, $rule['allowed_values'] ) ) {
                $errors[] = "Field '{$field}' has an invalid value";
            }

            // Check format
            if ( isset( $rule['format'] ) ) {
                if ( ! $this->validateFormat( $value, $rule['format'] ) ) {
                    $errors[] = "Field '{$field}' has an invalid format";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a value's format.
     */
    protected function validateFormat( mixed $value, string $format ): bool
    {
        // Reject non-scalar input up-front. The string-dependent checks
        // below (strtotime / ctype_*) emit warnings on arrays / objects
        // rather than returning false, so a non-scalar would slip past
        // as "valid" without this guard.
        if ( is_array( $value ) || is_object( $value ) ) {
            return false;
        }

        return match ( $format ) {
            'email'        => false !== filter_var( $value, FILTER_VALIDATE_EMAIL ),
            'phone'        => is_string( $value ) && (bool) preg_match( '/^[\d\s\-\+\(\)]+$/', $value ),
            'date'         => is_string( $value ) && false !== strtotime( $value ),
            'numeric'      => is_numeric( $value ),
            'alpha'        => is_string( $value ) && ctype_alpha( $value ),
            'alphanumeric' => is_string( $value ) && ctype_alnum( $value ),
            default        => true,
        };
    }
}
