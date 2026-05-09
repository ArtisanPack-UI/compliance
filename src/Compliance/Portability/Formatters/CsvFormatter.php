<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Portability\Formatters;

use RuntimeException;
use ZipArchive;

class CsvFormatter
{
    /**
     * Format data as CSV (zipped with multiple files).
     *
     * @param  array<string, mixed>  $data
     */
    public function format( array $data ): string
    {
        $tempDir = sys_get_temp_dir() . '/export_' . uniqid();
        mkdir( $tempDir, 0755, true );

        $zipPath = $tempDir . '.zip';

        $zip    = new ZipArchive;
        $result = $zip->open( $zipPath, ZipArchive::CREATE );
        if ( true !== $result ) {
            throw new RuntimeException( "Failed to create ZIP archive: error code {$result}" );
        }

        // Add export info as JSON
        if ( isset( $data['export_info'] ) ) {
            $zip->addFromString( 'export_info.json', json_encode( $data['export_info'], JSON_PRETTY_PRINT ) );
        }

        // Add each data category as separate CSV
        if ( isset( $data['data'] ) ) {
            foreach ( $data['data'] as $name => $categoryData ) {
                if ( isset( $categoryData['records'] ) && is_array( $categoryData['records'] ) ) {
                    $csv = $this->arrayToCsv( $categoryData['records'] );
                    $zip->addFromString( $name . '.csv', $csv );

                    // Also add schema
                    if ( isset( $categoryData['schema'] ) ) {
                        $zip->addFromString( $name . '_schema.json', json_encode( $categoryData['schema'], JSON_PRETTY_PRINT ) );
                    }
                }
            }
        }

        $zip->close();

        $content = file_get_contents( $zipPath );
        if ( false === $content ) {
            @unlink( $zipPath );
            @rmdir( $tempDir );
            throw new RuntimeException( 'Failed to read ZIP archive content' );
        }

        // Cleanup
        unlink( $zipPath );
        rmdir( $tempDir );

        return $content;
    }

    /**
     * Get MIME type.
     */
    public function getMimeType(): string
    {
        return 'application/zip';
    }

    /**
     * Convert array to CSV string.
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    protected function arrayToCsv( array $data ): string
    {
        if ( empty( $data ) ) {
            return '';
        }

        $output = fopen( 'php://temp', 'r+' );

        // Get headers from first row
        $first   = reset( $data );
        $headers = is_array( $first ) ? array_keys( $first ) : [];

        if ( ! empty( $headers ) ) {
            fputcsv( $output, $headers );
        }

        foreach ( $data as $row ) {
            if ( is_array( $row ) ) {
                // Flatten nested arrays
                $flatRow = array_map( function ( $value ) {
                    return is_array( $value ) ? json_encode( $value ) : $value;
                }, $row );
                fputcsv( $output, $flatRow );
            }
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return $csv;
    }
}
