<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Portability;

use ArtisanPackUI\Compliance\Compliance\Contracts\DataExporterInterface;
use ArtisanPackUI\Compliance\Events\DataExportCompleted;
use ArtisanPackUI\Compliance\Events\DataExportRequested;
use ArtisanPackUI\Compliance\Models\PortabilityRequest;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PortabilityService
{
    /**
     * @var array<string, DataExporterInterface>
     */
    protected array $exporters = [];

    protected DataPackager $packager;

    public function __construct( ?DataPackager $packager = null )
    {
        $this->packager = $packager ?? app()->make( DataPackager::class );
    }

    /**
     * Register a data exporter.
     */
    public function registerExporter( DataExporterInterface $exporter ): void
    {
        $this->exporters[ $exporter->getName() ] = $exporter;
    }

    /**
     * Get all registered exporters.
     *
     * @return array<string, DataExporterInterface>
     */
    public function getExporters(): array
    {
        return $this->exporters;
    }

    /**
     * Create a portability request.
     *
     * @param  array<string, mixed>  $options
     */
    public function createRequest( int $userId, array $options = [] ): PortabilityRequest
    {
        $defaultDeadlineDays = config( 'artisanpack.compliance.compliance.portability.default_deadline_days', 30 );
        $deadlineAt          = $options['deadline_at'] ?? Carbon::now()->addDays( $defaultDeadlineDays );

        $request = PortabilityRequest::create( [
            'user_id'         => $userId,
            'requester_type'  => $options['requester_type'] ?? 'self',
            'status'          => 'pending',
            'format'          => $options['format'] ?? config( 'artisanpack.compliance.compliance.portability.default_format', 'json' ),
            'categories'      => $options['categories'] ?? null,
            'transfer_type'   => $options['transfer_type'] ?? 'download',
            'destination_url' => $options['destination_url'] ?? null,
            'download_limit'  => config( 'artisanpack.compliance.compliance.portability.max_download_attempts', 5 ),
            'deadline_at'     => $deadlineAt,
            'created_by'      => auth()->id(),
        ] );

        event( new DataExportRequested( $request ) );

        return $request;
    }

    /**
     * Process a portability request.
     */
    public function processRequest( PortabilityRequest $request ): PortabilityRequest
    {
        $request->update( ['status' => 'processing'] );

        try {
            // Collect data from all exporters
            $data = $this->collectUserData( $request->user_id, $request->categories );

            // Package the data
            $package = $this->packager->package(
                $data,
                $request->format,
                $request->user_id,
            );

            // Store the package
            $path = $this->storeExport( $package, $request );

            // Calculate expiry
            $expiryHours = config( 'artisanpack.compliance.compliance.portability.download_expiry_hours', 72 );

            $request->update( [
                'status'       => 'completed',
                'file_path'    => $path,
                'file_size'    => $package->getSize(),
                'file_hash'    => $package->getHash(),
                'completed_at' => now(),
                'expires_at'   => now()->addHours( $expiryHours ),
            ] );

            event( new DataExportCompleted( $request ) );

            // Handle direct transfer if requested
            if ( 'direct_transfer' === $request->transfer_type && $request->destination_url ) {
                $this->transferToDestination( $request );
            }

            return $request->fresh();
        } catch ( Exception $e ) {
            // Log the exception with context before re-throwing
            Log::error( 'Portability request processing failed', [
                'request_id'     => $request->id,
                'request_number' => $request->request_number,
                'user_id'        => $request->user_id,
                'transfer_type'  => $request->transfer_type,
                'format'         => $request->format,
                'error'          => $e->getMessage(),
                'trace'          => $e->getTraceAsString(),
            ] );

            $request->update( [
                'status' => 'failed',
            ] );

            throw $e;
        }
    }

    /**
     * Collect user data from all exporters.
     *
     * @param  array<string>|null  $categories
     *
     * @return array<string, mixed>
     */
    public function collectUserData( int $userId, ?array $categories = null ): array
    {
        $data = [
            'export_info' => [
                'generated_at'   => now()->toIso8601String(),
                'format_version' => '1.0',
                'organization'   => config( 'app.name' ),
            ],
            'data' => [],
        ];

        foreach ( $this->exporters as $name => $exporter ) {
            // Skip if categories specified and exporter not in list
            if ( null !== $categories && ! in_array( $exporter->getCategory(), $categories ) ) {
                continue;
            }

            $exporterData = $exporter->getData( $userId );

            if ( $exporterData->isNotEmpty() ) {
                $data['data'][ $name ] = [
                    'category' => $exporter->getCategory(),
                    'schema'   => $exporter->getSchema(),
                    'records'  => $exporter->transform( $exporterData ),
                ];
            }
        }

        return $data;
    }

    /**
     * Preview what data will be exported.
     */
    public function previewExport( int $userId ): Collection
    {
        $preview = collect();

        foreach ( $this->exporters as $name => $exporter ) {
            $count = $exporter->getRecordCount( $userId );

            $preview->put( $name, [
                'name'         => $exporter->getName(),
                'category'     => $exporter->getCategory(),
                'record_count' => $count,
                'formats'      => $exporter->getSupportedFormats(),
            ] );
        }

        return $preview;
    }

    /**
     * Download an export.
     *
     * @return array{path: string, name: string}|null
     */
    public function download( PortabilityRequest $request ): ?array
    {
        if ( ! $request->canDownload() ) {
            return null;
        }

        $request->incrementDownloadCount();

        return [
            'path' => $request->file_path,
            'name' => $this->getDownloadFilename( $request ),
        ];
    }

    /**
     * Get available export categories.
     *
     * @return array<string>
     */
    public function getCategories(): array
    {
        $categories = [];

        foreach ( $this->exporters as $exporter ) {
            $category = $exporter->getCategory();
            if ( ! in_array( $category, $categories ) ) {
                $categories[] = $category;
            }
        }

        return $categories;
    }

    /**
     * Get supported formats.
     *
     * @return array<string>
     */
    public function getSupportedFormats(): array
    {
        return config( 'artisanpack.compliance.compliance.portability.supported_formats', ['json', 'xml', 'csv'] );
    }

    /**
     * Cleanup expired exports.
     */
    public function cleanupExpired(): int
    {
        $disk  = config( 'artisanpack.compliance.compliance.portability.storage_disk', 'local' );
        $count = 0;

        $expired = PortabilityRequest::expired()->get();

        foreach ( $expired as $request ) {
            if ( $request->file_path && Storage::disk( $disk )->exists( $request->file_path ) ) {
                Storage::disk( $disk )->delete( $request->file_path );
                $count++;
            }

            $request->update( [
                'status'    => 'expired',
                'file_path' => null,
            ] );
        }

        return $count;
    }

    /**
     * Store the export package.
     */
    protected function storeExport( ExportPackage $package, PortabilityRequest $request ): string
    {
        $disk     = config( 'artisanpack.compliance.compliance.portability.storage_disk', 'local' );
        $basePath = 'data-exports';

        $filename = sprintf(
            '%s-%s.%s',
            $request->request_number,
            now()->format( 'Y-m-d-His' ),
            $package->getExtension(),
        );

        $path = $basePath . '/' . $filename;

        Storage::disk( $disk )->put( $path, $package->getContent() );

        return $path;
    }

    /**
     * Transfer export to external destination.
     */
    protected function transferToDestination( PortabilityRequest $request ): void
    {
        // TODO: Implement direct transfer logic
        // This would typically use an HTTP client to POST the data
        // to the destination URL after verification
    }

    /**
     * Get download filename.
     */
    protected function getDownloadFilename( PortabilityRequest $request ): string
    {
        $extension = match ( $request->format ) {
            'json'  => 'json',
            'xml'   => 'xml',
            'csv'   => 'zip',
            default => 'json',
        };

        return sprintf(
            'data-export-%s.%s',
            $request->request_number,
            $extension,
        );
    }
}
