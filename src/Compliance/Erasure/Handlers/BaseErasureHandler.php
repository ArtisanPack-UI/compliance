<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Erasure\Handlers;

use ArtisanPackUI\Compliance\Compliance\Contracts\ErasureHandlerInterface;
use ArtisanPackUI\Compliance\Compliance\Erasure\ErasureHandlerResult;
use Illuminate\Support\Collection;

abstract class BaseErasureHandler implements ErasureHandlerInterface
{
    /**
     * Data categories handled by this handler.
     *
     * @var array<string>
     */
    protected array $dataCategories = [];

    /**
     * Retention exemption reasons.
     *
     * @var array<string>
     */
    protected array $exemptionReasons = [];

    /**
     * Whether this handler supports rollback.
     */
    protected bool $reversible = false;

    /**
     * Estimated time to complete in seconds.
     */
    protected int $estimatedTime = 30;

    /**
     * Get data categories handled.
     *
     * @return array<string>
     */
    public function getDataCategories(): array
    {
        return $this->dataCategories;
    }

    /**
     * Check if erasure is reversible.
     */
    public function isReversible(): bool
    {
        return $this->reversible;
    }

    /**
     * Get estimated time to complete.
     */
    public function getEstimatedTime(): int
    {
        return $this->estimatedTime;
    }

    /**
     * Rollback erasure (default implementation - override if reversible).
     *
     * @param  array<string, mixed>  $backupData
     */
    public function rollback( int $userId, array $backupData ): bool
    {
        return false;
    }

    /**
     * Check for retention exemptions.
     *
     * @return array<string, mixed>
     */
    protected function checkExemptions( int $userId ): array
    {
        return [];
    }

    /**
     * Create backup before erasure.
     *
     * @return array<string, mixed>
     */
    protected function createBackup( Collection $data ): array
    {
        if ( ! $this->reversible ) {
            return [];
        }

        return $data->toArray();
    }

    /**
     * Log erasure action.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logErasure( string $action, array $context = [] ): void
    {
        \Illuminate\Support\Facades\Log::info( "Erasure: {$action}", array_merge( [
            'handler' => $this->getName(),
        ], $context ) );
    }

    /**
     * Create a successful result.
     *
     * @param  array<string, mixed>  $backup
     */
    protected function success( int $found, int $erased, int $retained = 0, array $backup = [] ): ErasureHandlerResult
    {
        return ErasureHandlerResult::success( $found, $erased, $retained, $backup );
    }

    /**
     * Create a failed result.
     */
    protected function failed( string $error, int $found = 0 ): ErasureHandlerResult
    {
        return ErasureHandlerResult::failed( $error, $found );
    }

    /**
     * Create a partial result with retained records.
     *
     * @param  array<string, mixed>  $retained
     */
    protected function partial( int $found, int $erased, array $retained, string $reason ): ErasureHandlerResult
    {
        return ErasureHandlerResult::partial( $found, $erased, $retained, $reason);
    }
}
