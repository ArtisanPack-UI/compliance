<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Portability\Formatters;
use JsonException;
use RuntimeException;

class JsonFormatter
{
    /**
     * Format data as JSON.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException When JSON encoding fails
     */
    public function format( array $data ): string
    {
        return json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR );
    }

    /**
     * Parse JSON content.
     *
     * @return array<string, mixed>
     */
    public function parse( string $content ): array
    {
        $result = json_decode( $content, true, 512, JSON_THROW_ON_ERROR );
        
        if ( !is_array( $result ) ) {
            throw new RuntimeException( 'JSON content did not decode to an array' );
        }
        
        return $result;
    }

    /**
     * Get MIME type.
     */
    public function getMimeType(): string
    {
        return 'application/json';
    }
}
