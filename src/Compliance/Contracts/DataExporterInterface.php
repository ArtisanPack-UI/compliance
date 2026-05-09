<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Contracts;

use Illuminate\Support\Collection;

interface DataExporterInterface
{
    /**
     * Get exporter name.
     */
    public function getName(): string;

    /**
     * Get data category.
     */
    public function getCategory(): string;

    /**
     * Get exportable data for user.
     */
    public function getData( int $userId ): Collection;

    /**
     * Get data schema.
     *
     * @return array<string, mixed>
     */
    public function getSchema(): array;

    /**
     * Transform data for export.
     *
     * @return array<string, mixed>
     */
    public function transform( Collection $data ): array;

    /**
     * Get supported formats.
     *
     * @return array<string>
     */
    public function getSupportedFormats(): array;

    /**
     * Get estimated record count.
     */
    public function getRecordCount( int $userId ): int;
}
