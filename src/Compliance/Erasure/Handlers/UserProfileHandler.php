<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Erasure\Handlers;

use ArtisanPackUI\Compliance\Compliance\Erasure\ErasureHandlerResult;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserProfileHandler extends BaseErasureHandler
{
    protected array $dataCategories = ['identity', 'contact', 'profile'];

    protected bool $reversible = true;

    protected int $estimatedTime = 10;

    /**
     * Get handler name.
     */
    public function getName(): string
    {
        return 'user_profile';
    }

    /**
     * Get handler description.
     */
    public function getDescription(): string
    {
        return 'Handles user profile and account data erasure';
    }

    /**
     * Check if handler can process for user.
     */
    public function canHandle( int $userId ): bool
    {
        $userModel = config( 'auth.providers.users.model' );

        return null !== $userModel::find( $userId );
    }

    /**
     * Find all user data.
     */
    public function findUserData( int $userId ): Collection
    {
        $userModel = config( 'auth.providers.users.model' );
        $user      = $userModel::find( $userId );

        if ( ! $user ) {
            return collect();
        }

        return collect( [
            'user' => $user->toArray(),
        ] );
    }

    /**
     * Execute erasure.
     *
     * @param  array<string, mixed>  $options
     */
    public function erase( int $userId, array $options = [] ): ErasureHandlerResult
    {
        $userModel = config( 'auth.providers.users.model' );
        $user      = $userModel::find( $userId );

        if ( ! $user ) {
            return $this->failed( 'User not found', 0 );
        }

        $backup = $this->createBackup( collect( [$user->toArray()] ) );

        $this->logErasure( 'starting', ['user_id' => $userId] );

        try {
            // Anonymize rather than delete to maintain referential integrity
            $anonymizedEmail = 'deleted_' . Str::random( 16 ) . '@anonymized.local';
            $user->update( [
                'name'     => 'Deleted User',
                'email'    => $anonymizedEmail,
                'password' => Hash::make( Str::random( 64 ) ),
                // Add other fields as necessary
            ] );

            // Optionally soft delete if supported
            if ( method_exists( $user, 'trashed' ) && ! $user->trashed() ) {
                $user->delete();
            }

            $this->logErasure( 'completed', ['user_id' => $userId] );

            return $this->success( 1, 1, 0, $backup );
        } catch ( Exception $e ) {
            $this->logErasure( 'failed', ['user_id' => $userId, 'error' => $e->getMessage()] );

            return $this->failed( $e->getMessage(), 1 );
        }
    }

    /**
     * Rollback erasure.
     *
     * @param  array<string, mixed>  $backupData
     */
    public function rollback( int $userId, array $backupData ): bool
    {
        if ( empty( $backupData ) ) {
            return false;
        }

        $userModel = config( 'auth.providers.users.model' );
        $query     = $userModel::query();
        if ( method_exists( $userModel, 'withTrashed' ) ) {
            $query = $userModel::withTrashed();
        }
        $user = $query->find( $userId );

        if ( ! $user ) {
            return false;
        }

        // Restore soft deleted user
        if ( method_exists( $user, 'restore' ) ) {
            $user->restore();
        }

        // Restore original data
        $originalData = $backupData[0] ?? [];
        unset( $originalData['id'], $originalData['created_at'], $originalData['updated_at'], $originalData['deleted_at'] );

        $user->update( $originalData );

        return true;
    }
}
