<?php

/**
 * CheckConsentMiddleware HTTP middleware.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Middleware;

use ArtisanPackUI\Compliance\Compliance\Consent\ConsentManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckConsentMiddleware
{
    public function __construct( protected ConsentManager $consentManager )
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle( Request $request, Closure $next, string $purpose ): Response
    {
        $user = $request->user();

        if ( ! $user ) {
            return $next( $request );
        }

        $verification = $this->consentManager->verifyConsent( $user->id, $purpose );

        if ( ! $verification->isValid ) {
            if ( $verification->requiresReconsent ) {
                return response()->json( [
                    'error'   => 'reconsent_required',
                    'message' => 'Please update your consent preferences.',
                    'purpose' => $purpose,
                ], 403 );
            }

            return response()->json( [
                'error'   => 'consent_required',
                'message' => 'Consent is required to access this resource.',
                'purpose' => $purpose,
            ], 403 );
        }

        return $next( $request );
    }
}
