<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Traits;

use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

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
                // Sensitive attributes are encrypted at rest, so toArray()
                // gave us non-deterministic ciphertext. Hashing that would
                // produce a different pseudonym each time the row is
                // re-saved. Decrypt first so the same underlying value
                // always maps to the same pseudonym.
                $plain              = $this->decryptSensitiveAttribute( $attribute ) ?? $data[ $attribute ];
                $data[ $attribute ] = hash( 'sha256', $plain . $salt );
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
            if ( $this->attributeIsPresent( $attribute ) ) {
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
            if ( $this->attributeIsPresent( $attribute ) ) {
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
     * Whether the given attribute holds an actual value.
     *
     * Plain `! empty(...)` treats `'0'`, `0`, and `false` as missing —
     * which is wrong for personal/sensitive data where those are
     * legitimate values (a "0" survey answer, a `false` consent flag,
     * etc.). Use explicit null + empty-string checks instead.
     */
    protected function attributeIsPresent( string $attribute ): bool
    {
        if ( ! array_key_exists( $attribute, $this->attributes ) ) {
            return false;
        }

        $value = $this->attributes[ $attribute ];

        return null !== $value && '' !== $value;
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
            if ( ! $this->attributeIsPresent( $attribute ) ) {
                continue;
            }

            // Only encrypt values that look confidently like plaintext.
            // The previous "decrypt-or-encrypt" approach silently
            // re-encrypted legacy/malformed ciphertext (or values
            // encrypted under a rotated app key), corrupting the field.
            // Now: if the stored value parses as a Laravel encrypted
            // payload AND decrypts cleanly → leave alone. If it parses
            // as an encrypted payload but fails to decrypt → bail
            // loudly rather than re-encrypting opaque ciphertext.
            // Otherwise → treat as plaintext and encrypt.
            $value = $this->attributes[ $attribute ];

            // Crypt::encryptString and looksLikeEncryptedPayload both
            // require a string (declare(strict_types=1) is on for this
            // trait). Coerce scalars to string. Non-scalar values
            // (arrays, objects, resources) fail CLOSED with an explicit
            // exception — silently skipping them would leave the
            // attribute persisted in plaintext, which is the opposite
            // of what a privacy package should do. Apps storing
            // structured sensitive data should JSON-encode (or
            // otherwise serialize) before assigning to the attribute.
            if ( ! is_string( $value ) ) {
                if ( ! is_scalar( $value ) ) {
                    throw new InvalidArgumentException( sprintf(
                        'PrivacyByDesign: sensitive attribute "%s" on %s holds a non-scalar value (%s); '
                            . 'serialize it to a string before save (e.g. JSON-encode) so it can be encrypted.',
                        $attribute,
                        static::class,
                        get_debug_type( $value ),
                    ) );
                }
                $value = (string) $value;
            }

            if ( $this->looksLikeEncryptedPayload( $value ) ) {
                try {
                    Crypt::decryptString( $value );
                    // Already encrypted with the current key — leave it.
                    continue;
                } catch ( \Illuminate\Contracts\Encryption\DecryptException $e ) {
                    throw new RuntimeException(
                        "PrivacyByDesign: refusing to re-encrypt unreadable ciphertext for attribute '{$attribute}' "
                            . '(legacy payload or rotated app key). Re-encrypt manually after migrating the data.',
                        0,
                        $e,
                    );
                }
            }

            $this->attributes[ $attribute ] = Crypt::encryptString( $value );
        }
    }

    /**
     * Heuristic: does a string look like a Laravel encrypted payload?
     *
     * Laravel's Crypt::encryptString returns a base64-encoded JSON
     * blob with `iv`, `value`, and `mac` keys — recognising that
     * shape lets us distinguish "real ciphertext that won't decrypt"
     * from "user-supplied plaintext that happens to contain symbols".
     */
    protected function looksLikeEncryptedPayload( string $value ): bool
    {
        $decoded = base64_decode( $value, true );
        if ( false === $decoded ) {
            return false;
        }

        $json = json_decode( $decoded, true );

        return is_array( $json )
            && array_key_exists( 'iv', $json )
            && array_key_exists( 'value', $json )
            && array_key_exists( 'mac', $json );
    }

    /**
     * Decrypt a sensitive attribute.
     */
    protected function decryptSensitiveAttribute( string $attribute ): ?string
    {
        if ( ! $this->attributeIsPresent( $attribute ) ) {
            return null;
        }

        $value = $this->attributes[ $attribute ];

        // Skip values that aren't a Laravel encrypted payload at all —
        // an unsaved model or a redacted placeholder ('[REDACTED]')
        // would otherwise log a warning on every read despite the
        // caller already handling the null return gracefully.
        if ( ! is_string( $value ) || ! $this->looksLikeEncryptedPayload( $value ) ) {
            return null;
        }

        try {
            return Crypt::decryptString( $value );
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
