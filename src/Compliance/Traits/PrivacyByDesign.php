<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Traits;

use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait PrivacyByDesign
{
    /**
     * Pseudonymize personal data for analytics.
     *
     * @return array<string, mixed>
     */
    public function pseudonymize(): array
    {
        $data = $this->toArray();
        $salt = config( 'app.key' );

        foreach ( $this->getPersonalDataAttributes() as $attribute ) {
            if ( isset( $data[ $attribute ] ) ) {
                $data[ $attribute ] = hash( 'sha256', $data[ $attribute ] . $salt );
            }
        }

        foreach ( $this->getSensitiveDataAttributes() as $attribute ) {
            if ( isset( $data[ $attribute ] ) ) {
                $data[ $attribute ] = hash( 'sha256', $data[ $attribute ] . $salt );
            }
        }

        return $data;
    }

    /**
     * Get anonymized copy of the model.
     *
     * @return array<string, mixed>
     */
    public function anonymize(): array
    {
        $data = $this->toArray();

        foreach ( $this->getPersonalDataAttributes() as $attribute ) {
            if ( isset( $data[ $attribute ] ) ) {
                $data[ $attribute ] = $this->anonymizeValue( $data[ $attribute ], $attribute );
            }
        }

        foreach ( $this->getSensitiveDataAttributes() as $attribute ) {
            unset( $data[ $attribute ] );
        }

        return $data;
    }

    /**
     * Check if data can be collected for purpose.
     */
    public function canCollectFor( string $purpose ): bool
    {
        $rules = $this->getMinimizationRules();

        if ( empty( $rules ) ) {
            return true;
        }

        return isset( $rules[ $purpose ] ) && ! empty( $rules[ $purpose ]['allowed'] );
    }

    /**
     * Get all personal data from this model.
     *
     * @return array<string, mixed>
     */
    public function getPersonalData(): array
    {
        $data = [];

        foreach ( $this->getPersonalDataAttributes() as $attribute ) {
            if ( isset( $this->attributes[ $attribute ] ) ) {
                $data[ $attribute ] = $this->getAttribute( $attribute );
            }
        }

        return $data;
    }

    /**
     * Get all sensitive data from this model.
     *
     * @return array<string, mixed>
     */
    public function getSensitiveData(): array
    {
        $data = [];

        foreach ( $this->getSensitiveDataAttributes() as $attribute ) {
            if ( isset( $this->attributes[ $attribute ] ) ) {
                $value              = $this->decryptSensitiveAttribute( $attribute );
                $data[ $attribute ] = $value ?? $this->getAttribute( $attribute );
            }
        }

        return $data;
    }

    /**
     * Check if any personal data is present.
     */
    public function hasPersonalData(): bool
    {
        foreach ( $this->getPersonalDataAttributes() as $attribute ) {
            if ( ! empty( $this->attributes[ $attribute ] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if any sensitive data is present.
     */
    public function hasSensitiveData(): bool
    {
        foreach ( $this->getSensitiveDataAttributes() as $attribute ) {
            if ( ! empty( $this->attributes[ $attribute ] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redact personal data (replace with placeholders).
     */
    public function redactPersonalData(): void
    {
        foreach ( $this->getPersonalDataAttributes() as $attribute ) {
            if ( isset( $this->attributes[ $attribute ] ) ) {
                $this->attributes[ $attribute ] = '[REDACTED]';
            }
        }

        foreach ( $this->getSensitiveDataAttributes() as $attribute ) {
            if ( isset( $this->attributes[ $attribute ] ) ) {
                $this->attributes[ $attribute ] = '[REDACTED]';
            }
        }
    }

    /**
     * Define which attributes contain personal data.
     *
     * @return array<string>
     */
    protected function getPersonalDataAttributes(): array
    {
        return $this->personalDataAttributes ?? [];
    }

    /**
     * Define which attributes are sensitive/special category.
     *
     * @return array<string>
     */
    protected function getSensitiveDataAttributes(): array
    {
        return $this->sensitiveDataAttributes ?? [];
    }

    /**
     * Get data minimization rules.
     *
     * @return array<string, mixed>
     */
    protected function getMinimizationRules(): array
    {
        return $this->minimizationRules ?? [];
    }

    /**
     * Get retention periods for attributes.
     *
     * @return array<string, int|null>
     */
    protected function getRetentionPeriods(): array
    {
        return $this->retentionPeriods ?? [];
    }

    /**
     * Automatically encrypt sensitive attributes.
     *
     * Skips encryption for values that are already encrypted to prevent
     * double-encryption issues.
     */
    protected function encryptSensitiveData(): void
    {
        foreach ( $this->getSensitiveDataAttributes() as $attribute ) {
            if ( isset( $this->attributes[ $attribute ] ) && ! empty( $this->attributes[ $attribute ] ) ) {
                // Check if the value is already encrypted by attempting to decrypt it
                try {
                    Crypt::decryptString( $this->attributes[ $attribute ] );
                    // If decryption succeeds, the value is already encrypted - skip
                    continue;
                } catch ( \Illuminate\Contracts\Encryption\DecryptException $e ) {
                    // Decryption failed, meaning the value is not encrypted yet - proceed to encrypt
                    $this->attributes[ $attribute ] = Crypt::encryptString( $this->attributes[ $attribute ] );
                }
            }
        }
    }

    /**
     * Decrypt a sensitive attribute.
     */
    protected function decryptSensitiveAttribute( string $attribute ): ?string
    {
        if ( ! isset( $this->attributes[ $attribute ] ) || empty( $this->attributes[ $attribute ] ) ) {
            return null;
        }

        try {
            return Crypt::decryptString( $this->attributes[ $attribute ] );
        } catch ( Exception $e ) {
            Log::warning( "Failed to decrypt attribute {$attribute}", [
                'model' => static::class,
                'id'    => $this->getKey(),
            ] );

            return null;
        }
    }

    /**
     * Anonymize a single value.
     */
    protected function anonymizeValue( mixed $value, string $attribute ): mixed
    {
        if ( null === $value ) {
            return null;
        }

        // Check for email pattern
        if ( filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
            return $this->anonymizeEmail( $value );
        }

        // Check for phone pattern
        if ( is_string( $value ) && preg_match( '/^[\d\s\-\+\(\)]+$/', $value ) ) {
            return $this->anonymizePhone( $value );
        }

        // Default string anonymization
        if ( is_string( $value ) ) {
            return Str::mask( $value, '*', 0, max( 1, strlen( $value ) - 2 ) );
        }

        // For numbers, return a generalized range
        if ( is_numeric( $value ) ) {
            $order = pow( 10, max( 0, strlen( (string) (int) $value ) - 1 ) );

            return floor( $value / $order ) * $order;
        }

        return '[REDACTED]';
    }

    /**
     * Anonymize an email address.
     */
    protected function anonymizeEmail( string $email ): string
    {
        [$local, $domain] = explode( '@', $email );

        return Str::mask( $local, '*', 1 ) . '@' . $domain;
    }

    /**
     * Anonymize a phone number.
     */
    protected function anonymizePhone( string $phone ): string
    {
        $digits = preg_replace( '/[^\d]/', '', $phone );

        return Str::mask( $digits, '*', 0, -4 );
    }

    /**
     * Log data access for audit trail.
     */
    protected function logDataAccess( string $accessor, string $purpose ): void
    {
        if ( ! config( 'artisanpack.compliance.privacy_by_design.log_data_access', true ) ) {
            return;
        }

        Log::info( 'Personal data accessed', [
            'model'               => static::class,
            'record_id'           => $this->getKey(),
            'accessor'            => $accessor,
            'purpose'             => $purpose,
            'timestamp'           => now()->toIso8601String(),
            'personal_attributes' => $this->getPersonalDataAttributes(),
        ] );
    }
}
