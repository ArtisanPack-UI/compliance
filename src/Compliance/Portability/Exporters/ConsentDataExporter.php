<?php

/**
 * ConsentDataExporter component of the Compliance package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Portability\Exporters;

use ArtisanPackUI\Compliance\Models\ConsentRecord;
use Illuminate\Support\Collection;

class ConsentDataExporter extends BaseExporter
{
    protected string $category = 'consent';

    /**
     * Get exporter name.
     */
    public function getName(): string
    {
        return 'consent_records';
    }

    /**
     * Get exportable data for user.
     */
    public function getData( int $userId ): Collection
    {
        return ConsentRecord::where( 'user_id', $userId )->get();
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
                'purpose'           => ['type' => 'string', 'description' => 'Consent purpose'],
                'policy_version'    => ['type' => 'string', 'description' => 'Policy version'],
                'status'            => ['type' => 'string', 'enum' => ['granted', 'withdrawn', 'expired']],
                'consent_type'      => ['type' => 'string', 'enum' => ['explicit', 'implied']],
                'collection_method' => ['type' => 'string', 'description' => 'How consent was collected'],
                'granular_choices'  => ['type' => 'object', 'description' => 'Granular consent choices', 'nullable' => true],
                'granted_at'        => ['type' => 'string', 'format' => 'date-time'],
                'withdrawn_at'      => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'expires_at'        => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
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
        return [
            'purpose'           => $item->purpose,
            'policy_version'    => $item->policy_version,
            'status'            => $item->status,
            'consent_type'      => $item->consent_type,
            'collection_method' => $item->collection_method,
            'granular_choices'  => $item->granular_choices,
            'granted_at'        => $item->created_at?->toIso8601String(),
            'withdrawn_at'      => $item->withdrawn_at?->toIso8601String(),
            'expires_at'        => $item->expires_at?->toIso8601String(),
        ];
    }
}
