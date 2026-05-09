<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Portability\Exporters;

use Illuminate\Support\Collection;

class UserDataExporter extends BaseExporter
{
    protected string $category = 'profile';

    /**
     * Get exporter name.
     */
    public function getName(): string
    {
        return 'user_profile';
    }

    /**
     * Get exportable data for user.
     */
    public function getData( int $userId ): Collection
    {
        $userModel = config( 'auth.providers.users.model' );

        // Bail gracefully if the auth provider is misconfigured rather
        // than fatal-erroring on `null::find()`.
        if ( ! is_string( $userModel ) || '' === $userModel || ! class_exists( $userModel ) ) {
            return collect();
        }

        $user = $userModel::find( $userId );

        if ( ! $user ) {
            return collect();
        }

        return collect( [$user] );
    }

    /**
     * Get data schema.
     *
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id'                => ['type' => 'integer', 'description' => 'User ID'],
                'name'              => ['type' => 'string', 'description' => 'Full name'],
                'email'             => ['type' => 'string', 'format' => 'email', 'description' => 'Email address'],
                'email_verified_at' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Email verification date'],
                'created_at'        => ['type' => 'string', 'format' => 'date-time', 'description' => 'Account creation date'],
                'updated_at'        => ['type' => 'string', 'format' => 'date-time', 'description' => 'Last update date'],
            ],
        ];
    }

    /**
     * Transform a single item.
     *
     * @return array<string, mixed>
     */
    protected function transformItem( mixed $item ): array
    {
        $data = parent::transformItem( $item );

        // Allow-list export: only fields declared in getSchema()['properties']
        // ship to the user. A deny-list would silently leak any future
        // sensitive column added upstream (api_token, two_factor_secret,
        // recovery codes, etc.) which is a worse default for a privacy /
        // compliance package.
        $allowedFields = array_keys( $this->getSchema()['properties'] ?? [] );

        return array_intersect_key( $data, array_flip( $allowedFields ) );
    }
}
