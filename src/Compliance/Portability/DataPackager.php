<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Portability;

use ArtisanPackUI\Compliance\Compliance\Portability\Formatters\CsvFormatter;
use ArtisanPackUI\Compliance\Compliance\Portability\Formatters\JsonFormatter;
use ArtisanPackUI\Compliance\Compliance\Portability\Formatters\XmlFormatter;
use InvalidArgumentException;

class DataPackager
{
    protected JsonFormatter $jsonFormatter;

    protected XmlFormatter $xmlFormatter;

    protected CsvFormatter $csvFormatter;

    public function __construct(
        JsonFormatter $jsonFormatter,
        XmlFormatter $xmlFormatter,
        CsvFormatter $csvFormatter,
    ) {
        $this->jsonFormatter = $jsonFormatter;
        $this->xmlFormatter  = $xmlFormatter;
        $this->csvFormatter  = $csvFormatter;
    }

    /**
     * Package data for export.
     *
     * @param  array<string, mixed>  $data
     */
    public function package( array $data, string $format, int $userId ): ExportPackage
    {
        $content = match ( $format ) {
            'json'  => $this->jsonFormatter->format( $data ),
            'xml'   => $this->xmlFormatter->format( $data ),
            'csv'   => $this->csvFormatter->format( $data ),
            default => throw new InvalidArgumentException( "Unsupported format: {$format}" ),
        };

        $extension = match ( $format ) {
            'csv'   => 'zip', // CsvFormatter produces a ZIP archive
            default => $format,
        };

        return new ExportPackage(
            content: $content,
            format: $format,
            extension: $extension,
            hash: hash( 'sha256', $content ),
            size: strlen( $content ),
            userId: $userId,
        );
    }

    /**
     * Validate export schema compatibility.
     *
     * @param  array<string, mixed>  $data
     */
    public function validateSchema( array $data, string $format ): bool
    {
        // Basic validation - can be extended with JSON Schema, XSD, etc.
        return match ( $format ) {
            'json'  => $this->validateJsonSchema( $data ),
            'xml'   => $this->validateXmlSchema( $data ),
            'csv'   => $this->validateCsvSchema( $data ),
            default => false,
        };
    }

    /**
     * Validate JSON schema.
     *
     * @param  array<string, mixed>  $data
     */
    protected function validateJsonSchema( array $data ): bool
    {
        return isset( $data['export_info'] ) && isset( $data['data'] );
    }

    /**
     * Validate XML schema.
     *
     * @param  array<string, mixed>  $data
     */
    protected function validateXmlSchema( array $data ): bool
    {
        return isset( $data['export_info'] ) && isset( $data['data'] );
    }

    /**
     * Validate CSV schema.
     *
     * @param  array<string, mixed>  $data
     */
    protected function validateCsvSchema( array $data ): bool
    {
        return isset( $data['data'] );
    }
}
