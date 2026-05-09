<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Portability\Formatters;
use DOMDocument;
use InvalidArgumentException;
use SimpleXMLElement;

class XmlFormatter
{
    /**
     * Format data as XML.
     *
     * @param  array<string, mixed>  $data
     */
    public function format( array $data ): string
    {
        $xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><data_export></data_export>' );

        $this->arrayToXml( $data, $xml );

        $dom                     = new DOMDocument( '1.0', 'UTF-8' );
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = true;
        $dom->loadXML( $xml->asXML() );

        return $dom->saveXML();
    }

    /**
     * Parse XML content.
     *
     *
     * @throws InvalidArgumentException When the XML content is malformed
     *
     * @return array<string, mixed>
     */
    public function parse( string $content ): array
    {
        // Enable internal error handling to capture libxml errors
        $previousUseErrors = libxml_use_internal_errors( true );

        // Disable entity loader for PHP < 8.0 to prevent XXE attacks
        // In PHP 8.0+, external entity loading is disabled by default
        if ( PHP_VERSION_ID < 80000 ) {
            libxml_disable_entity_loader( true );
        }

        // Use LIBXML_NONET to block network fetches and prevent XXE
        // Do NOT use LIBXML_NOENT as it enables entity substitution which is a security risk
        $xml = simplexml_load_string( $content, SimpleXMLElement::class, LIBXML_NONET );

        if ( false === $xml ) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors( $previousUseErrors );

            $errorMessages = array_map(
                fn ( $error ) => trim( $error->message ),
                $errors,
            );

            throw new InvalidArgumentException(
                'Failed to parse XML content: ' . implode( '; ', $errorMessages ),
            );
        }

        libxml_clear_errors();
        libxml_use_internal_errors( $previousUseErrors );

        return json_decode( json_encode( $xml ), true );
    }

    /**
     * Get MIME type.
     */
    public function getMimeType(): string
    {
        return 'application/xml';
    }

    /**
     * Convert array to XML.
     *
     * @param  array<string, mixed>  $data
     */
    protected function arrayToXml( array $data, SimpleXMLElement &$xml ): void
    {
        foreach ( $data as $key => $value ) {
            // Handle numeric keys
            if ( is_numeric( $key ) ) {
                $key = 'item';
            }

            // Sanitize key name for XML
            $key = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $key );

            if ( is_array( $value ) ) {
                $subnode = $xml->addChild( $key );
                $this->arrayToXml( $value, $subnode );
            } else {
                $xml->addChild( $key, htmlspecialchars( (string) $value ) );
            }
        }
    }
}
