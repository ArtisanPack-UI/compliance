<?php

/**
 * ErasureHandlerResult component of the Compliance package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Erasure;

class ErasureHandlerResult
{
    /**
     * @param  array<string, mixed>  $backupData
     * @param  array<string, mixed>  $retainedRecords
     */
    public function __construct(
        public readonly bool $success,
        public readonly int $recordsFound = 0,
        public readonly int $recordsErased = 0,
        public readonly int $recordsRetained = 0,
        public readonly ?string $error = null,
        public readonly array $backupData = [],
        public readonly array $retainedRecords = [],
        public readonly ?string $retentionReason = null,
    ) {
    }

    /**
     * Create a successful result.
     *
     * @param  array<string, mixed>  $backupData
     */
    public static function success( int $found, int $erased, int $retained = 0, array $backupData = [] ): self
    {
        return new self(
            success: true,
            recordsFound: $found,
            recordsErased: $erased,
            recordsRetained: $retained,
            backupData: $backupData,
        );
    }

    /**
     * Create a failed result.
     */
    public static function failed( string $error, int $found = 0 ): self
    {
        return new self(
            success: false,
            recordsFound: $found,
            error: $error,
        );
    }

    /**
     * Create a result with retained records.
     *
     * @param  array<string, mixed>  $retained
     */
    public static function partial( int $found, int $erased, array $retained, string $reason ): self
    {
        return new self(
            success: true,
            recordsFound: $found,
            recordsErased: $erased,
            recordsRetained: count( $retained ),
            retainedRecords: $retained,
            retentionReason: $reason,
        );
    }

    /**
     * Check if all records were erased.
     */
    public function isComplete(): bool
    {
        return $this->success && 0 === $this->recordsRetained;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success'          => $this->success,
            'records_found'    => $this->recordsFound,
            'records_erased'   => $this->recordsErased,
            'records_retained' => $this->recordsRetained,
            'error'            => $this->error,
            'retention_reason' => $this->retentionReason,
        ];
    }
}
