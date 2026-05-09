<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Http\Controllers\Compliance;

use ArtisanPackUI\Compliance\Compliance\Consent\ConsentManager;
use ArtisanPackUI\Compliance\Compliance\Consent\ConsentPolicyService;
use ArtisanPackUI\Compliance\Compliance\Consent\CookieConsentHandler;
use ArtisanPackUI\Compliance\Models\ConsentPolicy;
use ArtisanPackUI\Compliance\Models\ConsentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ConsentController extends Controller
{
    public function __construct(
        protected ConsentManager $consentManager,
        protected ConsentPolicyService $policyService,
        protected CookieConsentHandler $cookieHandler,
    ) {
    }

    /**
     * Display the user's consent overview.
     */
    public function index( Request $request ): JsonResponse
    {
        $user = $request->user();

        $consents = ConsentRecord::where( 'user_id', $user->id )
            ->with( 'policy' )
            ->whereHas( 'policy' ) // Only include records with existing policies
            ->get()
            ->map( fn ( $record ) => [
                'id'           => $record->id,
                'policy_name'  => $record->policy?->name,
                'policy_slug'  => $record->policy?->slug,
                'status'       => $record->status,
                'granted_at'   => $record->granted_at?->toIso8601String(),
                'withdrawn_at' => $record->withdrawn_at?->toIso8601String(),
                'expires_at'   => $record->expires_at?->toIso8601String(),
            ] );

        return response()->json( [
            'success' => true,
            'data'    => [
                'consents' => $consents,
            ],
        ] );
    }

    /**
     * List all available consent policies.
     */
    public function policies( Request $request ): JsonResponse
    {
        $policies = ConsentPolicy::active()
            ->get()
            ->map( fn ( $policy ) => [
                'id'          => $policy->id,
                'name'        => $policy->name,
                'slug'        => $policy->slug,
                'description' => $policy->description,
                'version'     => $policy->version,
                'is_required' => $policy->is_required,
                'purposes'    => $policy->purposes,
            ] );

        return response()->json( [
            'success' => true,
            'data'    => [
                'policies' => $policies,
            ],
        ] );
    }

    /**
     * Show a specific consent policy.
     */
    public function showPolicy( ConsentPolicy $policy ): JsonResponse
    {
        return response()->json( [
            'success' => true,
            'data'    => [
                'policy' => [
                    'id'               => $policy->id,
                    'name'             => $policy->name,
                    'slug'             => $policy->slug,
                    'description'      => $policy->description,
                    'content'          => $policy->content,
                    'version'          => $policy->version,
                    'is_required'      => $policy->is_required,
                    'purposes'         => $policy->purposes,
                    'data_categories'  => $policy->data_categories,
                    'retention_period' => $policy->retention_period,
                    'third_parties'    => $policy->third_parties,
                ],
            ],
        ] );
    }

    /**
     * Grant consent to a policy.
     */
    public function grant( Request $request ): JsonResponse
    {
        $validated = $request->validate( [
            'policy_id' => 'required|exists:consent_policies,id',
            'purposes'  => 'nullable|array',
            'metadata'  => 'nullable|array',
        ] );

        $user   = $request->user();
        $policy = ConsentPolicy::findOrFail( $validated['policy_id'] );

        $record = $this->consentManager->grantConsent(
            $user,
            $policy,
            $validated['purposes'] ?? null,
            $validated['metadata'] ?? [],
        );

        return response()->json( [
            'success' => true,
            'message' => 'Consent granted successfully.',
            'data'    => [
                'consent_record' => [
                    'id'         => $record->id,
                    'policy_id'  => $record->consent_policy_id,
                    'status'     => $record->status,
                    'granted_at' => $record->granted_at->toIso8601String(),
                ],
            ],
        ] );
    }

    /**
     * Withdraw consent from a policy.
     */
    public function withdraw( Request $request ): JsonResponse
    {
        $validated = $request->validate( [
            'policy_id' => 'required|exists:consent_policies,id',
            'reason'    => 'nullable|string|max:500',
        ] );

        $user   = $request->user();
        $policy = ConsentPolicy::findOrFail( $validated['policy_id'] );

        $record = $this->consentManager->withdrawConsent(
            $user,
            $policy,
            $validated['reason'] ?? null,
        );

        if ( ! $record ) {
            return response()->json( [
                'success' => false,
                'message' => 'No active consent found to withdraw.',
            ], 404 );
        }

        return response()->json( [
            'success' => true,
            'message' => 'Consent withdrawn successfully.',
            'data'    => [
                'consent_record' => [
                    'id'           => $record->id,
                    'policy_id'    => $record->consent_policy_id,
                    'status'       => $record->status,
                    'withdrawn_at' => $record->withdrawn_at->toIso8601String(),
                ],
            ],
        ] );
    }

    /**
     * Get consent history for the current user.
     */
    public function history( Request $request ): JsonResponse
    {
        $user = $request->user();

        // Sanitize and bound the per_page parameter to prevent abuse
        $perPage = max( 1, min( (int) $request->input( 'per_page', 15 ), 100 ) );

        $history = ConsentRecord::where( 'user_id', $user->id )
            ->with( ['policy', 'auditLogs'] )
            ->orderBy( 'created_at', 'desc' )
            ->paginate( $perPage );

        return response()->json( [
            'success' => true,
            'data'    => [
                'history' => $history->through( fn ( $record ) => [
                    'id'           => $record->id,
                    'policy_name'  => $record->policy->name,
                    'status'       => $record->status,
                    'granted_at'   => $record->granted_at?->toIso8601String(),
                    'withdrawn_at' => $record->withdrawn_at?->toIso8601String(),
                    'audit_logs'   => $record->auditLogs->map( fn ( $log ) => [
                        'action'     => $log->action,
                        'created_at' => $log->created_at->toIso8601String(),
                    ] ),
                ] ),
                'pagination' => [
                    'current_page' => $history->currentPage(),
                    'last_page'    => $history->lastPage(),
                    'per_page'     => $history->perPage(),
                    'total'        => $history->total(),
                ],
            ],
        ] );
    }

    /**
     * Verify if the user has given consent to a policy.
     */
    public function verify( Request $request, ConsentPolicy $policy ): JsonResponse
    {
        $user = $request->user();

        $verification = $this->consentManager->verifyConsent( $user, $policy );

        return response()->json( [
            'success' => true,
            'data'    => [
                'has_consent'    => $verification->hasConsent,
                'is_valid'       => $verification->isValid,
                'expires_at'     => $verification->expiresAt?->toIso8601String(),
                'needs_renewal'  => $verification->needsRenewal,
                'policy_version' => $verification->policyVersion,
            ],
        ] );
    }

    /**
     * Get cookie consent preferences form.
     */
    public function cookiePreferences( Request $request ): JsonResponse
    {
        $categories         = $this->cookieHandler->getCategories();
        $currentPreferences = $this->cookieHandler->getCurrentPreferences( $request );

        return response()->json( [
            'success' => true,
            'data'    => [
                'categories'          => $categories,
                'current_preferences' => $currentPreferences,
            ],
        ] );
    }

    /**
     * Save cookie consent preferences.
     */
    public function saveCookiePreferences( Request $request ): JsonResponse
    {
        $validated = $request->validate( [
            'preferences'   => 'required|array',
            'preferences.*' => 'boolean',
        ] );

        $this->cookieHandler->savePreferences( $request, $validated['preferences'] );

        return response()->json( [
            'success' => true,
            'message' => 'Cookie preferences saved successfully.',
        ]);
    }
}
