<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Monitoring;

class CheckResult
{
    /**
     * @param  array<string, mixed>  $details
     * @param  array<string>  $violations
     * @param  array<string>  $warnings
     */
    public function __construct(
        public readonly string $status,
        public readonly ?float $score = null,
        public readonly int $itemsChecked = 0,
        public readonly int $itemsCompliant = 0,
        public readonly array $details = [],
        public readonly array $violations = [],
        public readonly array $warnings = [],
    ) {
    }

    /**
     * Create a passed result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function passed( int $checked = 0, int $compliant = 0, array $details = [] ): self
    {
        return new self(
            status: 'passed',
            score: $checked > 0 ? ( $compliant / $checked ) * 100 : 100,
            itemsChecked: $checked,
            itemsCompliant: $compliant,
            details: $details,
        );
    }

    /**
     * Create a failed result.
     *
     * @param  array<string>  $violations
     * @param  array<string, mixed>  $details
     */
    public static function failed( array $violations, int $checked = 0, int $compliant = 0, array $details = [] ): self
    {
        return new self(
            status: 'failed',
            score: $checked > 0 ? ( $compliant / $checked ) * 100 : 0,
            itemsChecked: $checked,
            itemsCompliant: $compliant,
            details: $details,
            violations: $violations,
        );
    }

    /**
     * Create a warning result.
     *
     * @param  array<string>  $warnings
     * @param  array<string, mixed>  $details
     */
    public static function warning( array $warnings, int $checked = 0, int $compliant = 0, array $details = [] ): self
    {
        return new self(
            status: 'warning',
            score: $checked > 0 ? ( $compliant / $checked ) * 100 : null,
            itemsChecked: $checked,
            itemsCompliant: $compliant,
            details: $details,
            warnings: $warnings,
        );
    }

    /**
     * Create an error result.
     */
    public static function error( string $message ): self
    {
        return new self(
            status: 'error',
            details: ['error' => $message],
        );
    }

    /**
     * Check if passed.
     */
    public function isPassed(): bool
    {
        return 'passed' === $this->status;
    }

    /**
     * Check if failed.
     */
    public function isFailed(): bool
    {
        return 'failed' === $this->status;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status'          => $this->status,
            'score'           => $this->score,
            'items_checked'   => $this->itemsChecked,
            'items_compliant' => $this->itemsCompliant,
            'details'         => $this->details,
            'violations'      => $this->violations,
            'warnings'        => $this->warnings,
        ];
    }
}
