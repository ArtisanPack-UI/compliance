<?php

/**
 * ErasureHandlerInterface contract — pluggable extension point for the compliance toolkit.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Contracts;

use ArtisanPackUI\Compliance\Compliance\Erasure\ErasureHandlerResult;
use Illuminate\Support\Collection;

interface ErasureHandlerInterface
{
    /**
     * Get handler name.
     */
    public function getName(): string;

    /**
     * Get handler description.
     */
    public function getDescription(): string;

    /**
     * Check if handler can process erasure for user.
     */
    public function canHandle( int $userId ): bool;

    /**
     * Find all data for user.
     */
    public function findUserData( int $userId ): Collection;

    /**
     * Execute erasure.
     *
     * @param  array<string, mixed>  $options
     */
    public function erase( int $userId, array $options = [] ): ErasureHandlerResult;

    /**
     * Check if erasure is reversible.
     */
    public function isReversible(): bool;

    /**
     * Rollback erasure if possible.
     *
     * @param  array<string, mixed>  $backupData
     */
    public function rollback( int $userId, array $backupData ): bool;

    /**
     * Get estimated time to complete in seconds.
     */
    public function getEstimatedTime(): int;

    /**
     * Get data categories handled.
     *
     * @return array<string>
     */
    public function getDataCategories(): array;
}
