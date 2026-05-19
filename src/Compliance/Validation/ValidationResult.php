<?php

/**
 * ValidationResult component of the Compliance package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Validation;

class ValidationResult
{
    /**
     * @param  array<string>  $errors
     * @param  array<string>  $warnings
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {
    }

    /**
     * Create a valid result.
     */
    public static function valid(): self
    {
        return new self( isValid: true );
    }

    /**
     * Create an invalid result.
     *
     * @param  array<string>  $errors
     */
    public static function invalid( array $errors ): self
    {
        return new self( isValid: false, errors: $errors );
    }

    /**
     * Check if there are any errors.
     */
    public function hasErrors(): bool
    {
        return ! empty( $this->errors );
    }

    /**
     * Check if there are any warnings.
     */
    public function hasWarnings(): bool
    {
        return ! empty( $this->warnings );
    }

    /**
     * Get all messages (errors + warnings).
     *
     * @return array<string>
     */
    public function getAllMessages(): array
    {
        return array_merge( $this->errors, $this->warnings );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid'    => $this->isValid,
            'errors'   => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
