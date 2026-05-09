<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Contracts;

use ArtisanPackUI\Compliance\Models\ConsentRecord;
use Illuminate\Support\Collection;

interface ConsentStorageInterface
{
    /**
     * Store a consent record.
     *
     * @param  array<string, mixed>  $data
     */
    public function store( array $data ): ConsentRecord;

    /**
     * Find consent record by user and purpose.
     */
    public function findByUserAndPurpose( int $userId, string $purpose ): ?ConsentRecord;

    /**
     * Get all consents for a user.
     */
    public function getByUser( int $userId ): Collection;

    /**
     * Update consent record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update( ConsentRecord $record, array $data ): ConsentRecord;

    /**
     * Delete consent record.
     */
    public function delete( ConsentRecord $record ): bool;

    /**
     * Get consent history for user and purpose.
     */
    public function getHistory( int $userId, ?string $purpose = null ): Collection;

    /**
     * Get users with expired consent for purpose.
     */
    public function getExpiredConsents( string $purpose ): Collection;
}
