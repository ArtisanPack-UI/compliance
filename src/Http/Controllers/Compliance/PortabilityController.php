<?php

/**
 * PortabilityController HTTP controller.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Http\Controllers\Compliance;

use ArtisanPackUI\Compliance\Compliance\Portability\PortabilityService;
use ArtisanPackUI\Compliance\Events\DataExportRequested;
use ArtisanPackUI\Compliance\Jobs\ProcessPortabilityRequestJob;
use ArtisanPackUI\Compliance\Models\PortabilityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortabilityController extends Controller
{
    public function __construct(
        protected PortabilityService $portabilityService,
    ) {
    }

    /**
     * Display the user's portability requests.
     */
    public function index( Request $request ): JsonResponse
    {
        $user = $request->user();

        $requests = PortabilityRequest::where( 'user_id', $user->id )
            ->orderBy( 'created_at', 'desc' )
            ->paginate( $request->input( 'per_page', 15 ) );

        return response()->json( [
            'success' => true,
            'data'    => [
                'requests' => $requests->through( fn ( $req ) => [
                    'id'             => $req->id,
                    'request_number' => $req->request_number,
                    'status'         => $req->status,
                    'format'         => $req->format,
                    'download_count' => $req->download_count,
                    'download_limit' => $req->download_limit,
                    'expires_at'     => $req->expires_at?->toIso8601String(),
                    'completed_at'   => $req->completed_at?->toIso8601String(),
                    'created_at'     => $req->created_at->toIso8601String(),
                    'can_download'   => 'completed' === $req->status
                        && $req->download_count < $req->download_limit
                        && ( ! $req->expires_at || $req->expires_at->isFuture() ),
                ] ),
                'pagination' => [
                    'current_page' => $requests->currentPage(),
                    'last_page'    => $requests->lastPage(),
                    'per_page'     => $requests->perPage(),
                    'total'        => $requests->total(),
                ],
            ],
        ] );
    }

    /**
     * Create a new portability request.
     */
    public function request( Request $request ): JsonResponse
    {
        $validated = $request->validate( [
            'format'            => 'required|in:json,xml,csv',
            'data_categories'   => 'nullable|array',
            'data_categories.*' => 'string|max:100',
            'include_metadata'  => 'nullable|boolean',
        ] );

        $user = $request->user();

        // Check for pending requests
        $pendingRequest = PortabilityRequest::where( 'user_id', $user->id )
            ->whereIn( 'status', ['pending', 'processing'] )
            ->first();

        if ( $pendingRequest ) {
            return response()->json( [
                'success' => false,
                'message' => 'You already have a pending data export request.',
                'data'    => [
                    'existing_request' => [
                        'request_number' => $pendingRequest->request_number,
                        'status'         => $pendingRequest->status,
                    ],
                ],
            ], 409 );
        }

        // Check rate limit for exports
        $recentExports = PortabilityRequest::where( 'user_id', $user->id )
            ->where( 'created_at', '>=', now()->subDays( 30 ) )
            ->count();

        $maxExportsPerMonth = config( 'artisanpack.compliance.portability.max_exports_per_month', 5 );

        if ( $recentExports >= $maxExportsPerMonth ) {
            return response()->json( [
                'success' => false,
                'message' => "You have reached the maximum of {$maxExportsPerMonth} export requests per month.",
            ], 429 );
        }

        // Create portability request
        $portabilityRequest = PortabilityRequest::create( [
            'request_number'   => 'PRT-' . strtoupper( Str::random( 10 ) ),
            'user_id'          => $user->id,
            'status'           => 'pending',
            'format'           => $validated['format'],
            'data_categories'  => $validated['data_categories'] ?? null,
            'include_metadata' => $validated['include_metadata'] ?? true,
            'download_limit'   => config( 'artisanpack.compliance.portability.download_limit', 3 ),
            'download_count'   => 0,
            'ip_address'       => $request->ip(),
            'user_agent'       => $request->userAgent(),
        ] );

        // Fire event
        event( new DataExportRequested( $portabilityRequest ) );

        // Dispatch processing job
        ProcessPortabilityRequestJob::dispatch( $portabilityRequest );

        return response()->json( [
            'success' => true,
            'message' => 'Data export request submitted successfully. You will be notified when it\'s ready.',
            'data'    => [
                'request' => [
                    'request_number' => $portabilityRequest->request_number,
                    'status'         => $portabilityRequest->status,
                    'format'         => $portabilityRequest->format,
                ],
            ],
        ], 201 );
    }

    /**
     * Get the status of a specific portability request.
     */
    public function status( Request $request, PortabilityRequest $portabilityRequest ): JsonResponse
    {
        $user = $request->user();

        // Ensure user can only access their own requests
        if ( $portabilityRequest->user_id !== $user->id ) {
            return response()->json( [
                'success' => false,
                'message' => 'Export request not found.',
            ], 404 );
        }

        return response()->json( [
            'success' => true,
            'data'    => [
                'request' => [
                    'request_number'      => $portabilityRequest->request_number,
                    'status'              => $portabilityRequest->status,
                    'format'              => $portabilityRequest->format,
                    'data_categories'     => $portabilityRequest->data_categories,
                    'file_size'           => $portabilityRequest->file_size,
                    'file_size_formatted' => $this->formatBytes( $portabilityRequest->file_size ),
                    'download_count'      => $portabilityRequest->download_count,
                    'download_limit'      => $portabilityRequest->download_limit,
                    'expires_at'          => $portabilityRequest->expires_at?->toIso8601String(),
                    'completed_at'        => $portabilityRequest->completed_at?->toIso8601String(),
                    'error_message'       => $portabilityRequest->error_message,
                    'created_at'          => $portabilityRequest->created_at->toIso8601String(),
                ],
                'can_download' => 'completed' === $portabilityRequest->status
                    && $portabilityRequest->download_count < $portabilityRequest->download_limit
                    && ( ! $portabilityRequest->expires_at || $portabilityRequest->expires_at->isFuture() ),
            ],
        ] );
    }

    /**
     * Download the exported data.
     */
    public function download( Request $request, PortabilityRequest $portabilityRequest ): JsonResponse|StreamedResponse
    {
        $user = $request->user();

        // Ensure user can only download their own exports
        if ( $portabilityRequest->user_id !== $user->id ) {
            return response()->json( [
                'success' => false,
                'message' => 'Export request not found.',
            ], 404 );
        }

        // Check if export is ready
        if ( 'completed' !== $portabilityRequest->status ) {
            return response()->json( [
                'success' => false,
                'message' => 'Export is not ready for download.',
            ], 422 );
        }

        // Check download limit
        if ( $portabilityRequest->download_count >= $portabilityRequest->download_limit ) {
            return response()->json( [
                'success' => false,
                'message' => 'Download limit reached.',
            ], 403 );
        }

        // Check expiration
        if ( $portabilityRequest->expires_at && $portabilityRequest->expires_at->isPast() ) {
            return response()->json( [
                'success' => false,
                'message' => 'Download link has expired.',
            ], 410 );
        }

        // Check if file exists
        $filePath = $portabilityRequest->file_path;
        $disk     = config( 'artisanpack.compliance.portability.storage_disk', 'local' );

        if ( ! Storage::disk( $disk )->exists( $filePath ) ) {
            return response()->json( [
                'success' => false,
                'message' => 'Export file not found. Please request a new export.',
            ], 404 );
        }

        // Increment download count
        $portabilityRequest->increment( 'download_count' );
        $portabilityRequest->update( [
            'last_downloaded_at' => now(),
        ] );

        // Get file extension based on format
        $extension = match ( $portabilityRequest->format ) {
            'json'  => 'json',
            'xml'   => 'xml',
            'csv'   => 'zip', // CSV exports are zipped
            default => 'zip',
        };

        $filename = "data-export-{$portabilityRequest->request_number}.{$extension}";

        // Stream the file download
        return Storage::disk( $disk )->download( $filePath, $filename, [
            'Content-Type' => $this->getContentType( $extension ),
        ] );
    }

    /**
     * Format bytes to human readable size.
     */
    protected function formatBytes( ?int $bytes ): ?string
    {
        if ( null === $bytes ) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i     = 0;

        while ( $bytes >= 1024 && $i < count( $units ) - 1 ) {
            $bytes /= 1024;
            $i++;
        }

        return round( $bytes, 2 ) . ' ' . $units[ $i ];
    }

    /**
     * Get content type for file extension.
     */
    protected function getContentType( string $extension ): string
    {
        return match ( $extension ) {
            'json'  => 'application/json',
            'xml'   => 'application/xml',
            'csv'   => 'text/csv',
            'zip'   => 'application/zip',
            default => 'application/octet-stream',
        };
    }
}
