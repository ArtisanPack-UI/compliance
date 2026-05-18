<?php

/**
 * ErasureController HTTP controller.
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

use ArtisanPackUI\Compliance\Compliance\Erasure\ErasureService;
use ArtisanPackUI\Compliance\Events\ErasureRequested;
use ArtisanPackUI\Compliance\Models\ErasureRequest;
use Cache;
use Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class ErasureController extends Controller
{
    public function __construct(
        protected ErasureService $erasureService,
    ) {
    }

    /**
     * Display the user's erasure requests.
     */
    public function index( Request $request ): JsonResponse
    {
        $user = $request->user();

        $perPage = min( (int) $request->input( 'per_page', 15 ), 100 );

        $requests = ErasureRequest::where( 'user_id', $user->id )
            ->orderBy( 'created_at', 'desc' )
            ->paginate( $perPage );

        return response()->json( [
            'success' => true,
            'data'    => [
                'requests' => $requests->through( fn ( $req ) => [
                    'id'             => $req->id,
                    'request_number' => $req->request_number,
                    'status'         => $req->status,
                    'scope'          => $req->scope,
                    'scheduled_at'   => $req->scheduled_at?->toIso8601String(),
                    'completed_at'   => $req->completed_at?->toIso8601String(),
                    'created_at'     => $req->created_at->toIso8601String(),
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
     * Create a new erasure request.
     */
    public function request( Request $request ): JsonResponse
    {
        $validated = $request->validate( [
            'scope'               => 'required|in:full,partial',
            'data_categories'     => 'required_if:scope,partial|array',
            'data_categories.*'   => 'string|max:100',
            'reason'              => 'nullable|string|max:1000',
            'verification_method' => 'required|in:password,email,two_factor',
            'verification_value'  => 'required|string',
        ] );

        $user = $request->user();

        // Verify user identity based on verification method
        $verified = $this->verifyIdentity(
            $user,
            $validated['verification_method'],
            $validated['verification_value'],
        );

        if ( ! $verified ) {
            return response()->json( [
                'success' => false,
                'message' => 'Identity verification failed.',
            ], 403 );
        }

        // Check for pending requests
        $pendingRequest = ErasureRequest::where( 'user_id', $user->id )
            ->whereIn( 'status', ['pending', 'processing', 'scheduled'] )
            ->first();

        if ( $pendingRequest ) {
            return response()->json( [
                'success' => false,
                'message' => 'You already have a pending erasure request.',
                'data'    => [
                    'existing_request' => [
                        'request_number' => $pendingRequest->request_number,
                        'status'         => $pendingRequest->status,
                    ],
                ],
            ], 409 );
        }

        // Calculate scheduled date based on grace period
        $gracePeriodDays = config( 'artisanpack.compliance.erasure.grace_period_days', 30 );
        $scheduledAt     = now()->addDays( $gracePeriodDays );

        // Create erasure request
        $erasureRequest = ErasureRequest::create( [
            'request_number'  => 'ERA-' . strtoupper( Str::random( 10 ) ),
            'user_id'         => $user->id,
            'status'          => 'pending',
            'scope'           => $validated['scope'],
            'data_categories' => $validated['data_categories'] ?? null,
            'reason'          => $validated['reason'] ?? null,
            'scheduled_at'    => $scheduledAt,
            'ip_address'      => $request->ip(),
            'user_agent'      => $request->userAgent(),
        ] );

        // Fire event
        event( new ErasureRequested( $erasureRequest ) );

        return response()->json( [
            'success' => true,
            'message' => 'Erasure request submitted successfully.',
            'data'    => [
                'request' => [
                    'request_number'    => $erasureRequest->request_number,
                    'status'            => $erasureRequest->status,
                    'scope'             => $erasureRequest->scope,
                    'scheduled_at'      => $erasureRequest->scheduled_at->toIso8601String(),
                    'grace_period_days' => $gracePeriodDays,
                ],
            ],
        ], 201 );
    }

    /**
     * Get the status of a specific erasure request.
     */
    public function status( Request $request, ErasureRequest $erasureRequest ): JsonResponse
    {
        $user = $request->user();

        // Ensure user can only access their own requests
        if ( $erasureRequest->user_id !== $user->id ) {
            return response()->json( [
                'success' => false,
                'message' => 'Erasure request not found.',
            ], 404 );
        }

        $logs = $erasureRequest->logs()
            ->orderBy( 'created_at', 'desc' )
            ->limit( 10 )
            ->get();

        return response()->json( [
            'success' => true,
            'data'    => [
                'request' => [
                    'request_number'  => $erasureRequest->request_number,
                    'status'          => $erasureRequest->status,
                    'scope'           => $erasureRequest->scope,
                    'data_categories' => $erasureRequest->data_categories,
                    'scheduled_at'    => $erasureRequest->scheduled_at?->toIso8601String(),
                    'started_at'      => $erasureRequest->started_at?->toIso8601String(),
                    'completed_at'    => $erasureRequest->completed_at?->toIso8601String(),
                    'error_message'   => $erasureRequest->error_message,
                    'created_at'      => $erasureRequest->created_at->toIso8601String(),
                ],
                'logs' => $logs->map( fn ( $log ) => [
                    'handler'          => $log->handler_class,
                    'status'           => $log->status,
                    'records_affected' => $log->records_affected,
                    'created_at'       => $log->created_at->toIso8601String(),
                ] ),
                'can_cancel' => in_array( $erasureRequest->status, ['pending', 'scheduled'] ),
            ],
        ] );
    }

    /**
     * Cancel a pending erasure request.
     */
    public function cancel( Request $request, ErasureRequest $erasureRequest ): JsonResponse
    {
        $user = $request->user();

        // Ensure user can only cancel their own requests
        if ( $erasureRequest->user_id !== $user->id ) {
            return response()->json( [
                'success' => false,
                'message' => 'Erasure request not found.',
            ], 404 );
        }

        // Check if request can be cancelled
        if ( ! in_array( $erasureRequest->status, ['pending', 'scheduled'] ) ) {
            return response()->json( [
                'success' => false,
                'message' => 'This erasure request cannot be cancelled.',
            ], 422 );
        }

        $validated = $request->validate( [
            'reason' => 'nullable|string|max:1000',
        ] );

        $erasureRequest->update( [
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => $validated['reason'] ?? null,
        ] );

        return response()->json( [
            'success' => true,
            'message' => 'Erasure request cancelled successfully.',
            'data'    => [
                'request' => [
                    'request_number' => $erasureRequest->request_number,
                    'status'         => $erasureRequest->status,
                ],
            ],
        ] );
    }

    /**
     * Verify user identity for erasure request.
     */
    protected function verifyIdentity( $user, string $method, string $value ): bool
    {
        return match ( $method ) {
            'password'   => Hash::check( $value, $user->password ),
            'email'      => $this->verifyEmailCode( $user, $value ),
            'two_factor' => $this->verifyTwoFactorCode( $user, $value ),
            default      => false,
        };
    }

    /**
     * Verify email verification code.
     */
    protected function verifyEmailCode( $user, string $code ): bool
    {
        $cacheKey   = 'erasure_verification_' . $user->id;
        $storedCode = Cache::get( $cacheKey );

        if ( $storedCode && hash_equals( $storedCode, $code ) ) {
            Cache::forget( $cacheKey );
            return true;
        }

        return false;
    }

    /**
     * Verify two-factor authentication code.
     */
    protected function verifyTwoFactorCode( $user, string $code ): bool
    {
        if ( ! method_exists( $user, 'verifyTwoFactorCode' ) ) {
            return false;
        }

        return $user->verifyTwoFactorCode( $code);
    }
}
