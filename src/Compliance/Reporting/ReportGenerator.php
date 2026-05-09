<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Reporting;

use ArtisanPackUI\Compliance\Compliance\Contracts\ReportTypeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ReportGenerator
{
    /**
     * @var array<string, ReportTypeInterface>
     */
    protected array $reportTypes = [];

    /**
     * Register a report type.
     */
    public function registerReportType( ReportTypeInterface $reportType ): void
    {
        $this->reportTypes[ $reportType->getName() ] = $reportType;
    }

    /**
     * Get all available report types.
     *
     * @return array<string, ReportTypeInterface>
     */
    public function getReportTypes(): array
    {
        return $this->reportTypes;
    }

    /**
     * Generate a report.
     *
     * @param  array<string, mixed>  $options
     */
    public function generate( string $type, array $options = [] ): ComplianceReport
    {
        if ( ! isset( $this->reportTypes[ $type ] ) ) {
            throw new InvalidArgumentException( "Unknown report type: {$type}" );
        }

        return $this->reportTypes[ $type ]->generate( $options );
    }

    /**
     * Export report to file.
     */
    public function export( ComplianceReport $report, string $format = 'json' ): string
    {
        $content = match ( $format ) {
            'json'  => $report->toJson(),
            'html'  => $this->renderHtml( $report ),
            'csv'   => $this->renderCsv( $report ),
            default => throw new InvalidArgumentException( "Unsupported format: {$format}" ),
        };

        $filename = sprintf(
            '%s-%s.%s',
            $report->type,
            now()->format( 'Y-m-d-His' ),
            $format,
        );

        $path = config( 'artisanpack.compliance.compliance.reporting.storage_path', 'compliance-reports' ) . '/' . $filename;
        $disk = config( 'artisanpack.compliance.compliance.reporting.storage_disk', 'local' );

        Storage::disk( $disk )->put( $path, $content );

        return $path;
    }

    /**
     * Get report history.
     */
    public function getHistory( ?string $type = null, int $limit = 20 ): Collection
    {
        $disk = config( 'artisanpack.compliance.compliance.reporting.storage_disk', 'local' );
        $path = config( 'artisanpack.compliance.compliance.reporting.storage_path', 'compliance-reports' );

        $files = Storage::disk( $disk )->files( $path );

        $reports = collect( $files )->map( function ( $file ) use ( $disk ) {
            $parts = pathinfo( $file );

            return [
                'path'       => $file,
                'filename'   => $parts['basename'],
                'type'       => explode( '-', $parts['filename'] )[0] ?? 'unknown',
                'format'     => $parts['extension'] ?? 'json',
                'size'       => Storage::disk( $disk )->size( $file ),
                'created_at' => Storage::disk( $disk )->lastModified( $file ),
            ];
        } )->sortByDesc( 'created_at' );

        if ( $type ) {
            $reports = $reports->filter( fn ( $r ) => $r['type'] === $type );
        }

        return $reports->take( $limit )->values();
    }

    /**
     * Render report as HTML.
     */
    protected function renderHtml( ComplianceReport $report ): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<title>' . htmlspecialchars( $report->title ) . '</title>';
        $html .= '<style>body{font-family:sans-serif;margin:40px}h1{color:#333}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f4f4f4}</style>';
        $html .= '</head><body>';
        $html .= '<h1>' . htmlspecialchars( $report->title ) . '</h1>';
        $html .= '<p>Generated: ' . htmlspecialchars( $report->generatedAt->format( 'Y-m-d H:i:s' ) ) . '</p>';

        foreach ( $report->sections as $name => $section ) {
            $html .= '<h2>' . htmlspecialchars( ucfirst( str_replace( '_', ' ', $name ) ) ) . '</h2>';
            $html .= $this->renderSectionHtml( $section );
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Render a section as HTML.
     *
     * @param  array<string, mixed>|mixed  $section
     */
    protected function renderSectionHtml( mixed $section ): string
    {
        if ( is_array( $section ) ) {
            if ( $this->isAssociativeArray( $section ) ) {
                $html = '<table>';
                foreach ( $section as $key => $value ) {
                    $html .= '<tr><th>' . htmlspecialchars( ucfirst( str_replace( '_', ' ', $key ) ) ) . '</th>';
                    $html .= '<td>' . $this->renderValue( $value ) . '</td></tr>';
                }
                $html .= '</table>';

                return $html;
            }

            $html = '<ul>';
            foreach ( $section as $item ) {
                $html .= '<li>' . $this->renderValue( $item ) . '</li>';
            }
            $html .= '</ul>';

            return $html;
        }

        return htmlspecialchars( (string) $section );
    }

    /**
     * Render value as HTML.
     */
    protected function renderValue( mixed $value ): string
    {
        if ( is_array( $value ) ) {
            return $this->renderSectionHtml( $value );
        }

        if ( is_bool( $value ) ) {
            return $value ? 'Yes' : 'No';
        }

        return htmlspecialchars( (string) $value );
    }

    /**
     * Render report as CSV.
     */
    protected function renderCsv( ComplianceReport $report ): string
    {
        $output = fopen( 'php://temp', 'r+' );

        fputcsv( $output, ['Report Type', $report->type] );
        fputcsv( $output, ['Title', $report->title] );
        fputcsv( $output, ['Generated At', $report->generatedAt->format( 'Y-m-d H:i:s' )] );
        fputcsv( $output, [] );

        foreach ( $report->sections as $name => $section ) {
            fputcsv( $output, ['--- ' . strtoupper( $name ) . ' ---'] );
            $this->renderSectionCsv( $output, $section );
            fputcsv( $output, [] );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return $csv;
    }

    /**
     * Render section as CSV.
     *
     * @param  resource  $output
     * @param  array<string, mixed>|mixed  $section
     */
    protected function renderSectionCsv( $output, mixed $section, string $prefix = '' ): void
    {
        if ( is_array( $section ) ) {
            foreach ( $section as $key => $value ) {
                if ( is_array( $value ) ) {
                    fputcsv( $output, [$prefix . $key, json_encode( $value )] );
                } else {
                    fputcsv( $output, [$prefix . $key, $value] );
                }
            }
        }
    }

    /**
     * Check if array is associative.
     *
     * @param  array<mixed>  $array
     */
    protected function isAssociativeArray( array $array ): bool
    {
        return array_keys( $array ) !== range( 0, count( $array) - 1);
    }
}
