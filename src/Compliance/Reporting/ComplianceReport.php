<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Reporting;
use DateTimeInterface;

class ComplianceReport
{
    public readonly DateTimeInterface $generatedAt;

    /**
     * @param  array<string, mixed>  $sections
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly array $sections,
        public readonly array $metadata = [],
        ?DateTimeInterface $generatedAt = null,
    ) {
        $this->generatedAt = $generatedAt ?? now();
    }

    /**
     * Get section by name.
     *
     * @return array<string, mixed>|null
     */
    public function getSection( string $name ): ?array
    {
        return $this->sections[ $name ] ?? null;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type'         => $this->type,
            'title'        => $this->title,
            'generated_at' => $this->generatedAt->format( 'c' ),
            'metadata'     => $this->metadata,
            'sections'     => $this->sections,
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson(): string
    {
        return json_encode( $this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }
}
