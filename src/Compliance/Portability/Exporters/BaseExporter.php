<?php

/**
 * BaseExporter component of the Compliance package.
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

use ArtisanPackUI\Compliance\Compliance\Contracts\DataExporterInterface;
use Illuminate\Support\Collection;

abstract class BaseExporter implements DataExporterInterface
{
    /**
     * Data category.
     */
    protected string $category = 'general';

    /**
     * Supported formats.
     *
     * @var array<string>
     */
    protected array $supportedFormats = ['json', 'xml', 'csv'];

    /**
     * Get data category.
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Get supported formats.
     *
     * @return array<string>
     */
    public function getSupportedFormats(): array
    {
        return $this->supportedFormats;
    }

    /**
     * Transform data for export.
     *
     * @return array<string, mixed>
     */
    public function transform( Collection $data ): array
    {
        return $data->map( fn ( $item ) => $this->transformItem( $item ) )->toArray();
    }

    /**
     * Get record count for user.
     */
    public function getRecordCount( int $userId ): int
    {
        return $this->getData( $userId )->count();
    }

    /**
     * Transform a single item.
     *
     * @return array<string, mixed>
     */
    protected function transformItem( mixed $item ): array
    {
        if ( is_array( $item ) ) {
            return $item;
        }

        if ( method_exists( $item, 'toArray' ) ) {
            return $item->toArray();
        }

        return (array) $item;
    }
}
