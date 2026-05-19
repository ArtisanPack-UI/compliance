<?php

/**
 * PseudonymizedResult component of the Compliance package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Minimization;

use Illuminate\Support\Collection;

class PseudonymizedResult
{
    public function __construct(
        public readonly Collection $data,
        public readonly string $mappingKey,
    ) {
    }

    /**
     * Get the pseudonymized data.
     */
    public function getData(): Collection
    {
        return $this->data;
    }

    /**
     * Get the mapping key for de-pseudonymization.
     */
    public function getMappingKey(): string
    {
        return $this->mappingKey;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data'        => $this->data->toArray(),
            'mapping_key' => $this->mappingKey,
        ];
    }
}
