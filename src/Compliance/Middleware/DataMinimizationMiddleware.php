<?php

/**
 * DataMinimizationMiddleware HTTP middleware.
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

use ArtisanPackUI\Compliance\Compliance\Minimization\DataMinimizerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DataMinimizationMiddleware
{
    public function __construct( protected DataMinimizerService $minimizer )
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle( Request $request, Closure $next, string $purpose ): Response
    {
        // Validate collection if configured
        if ( config( 'artisanpack.compliance.minimization.enforce_collection_policies', true ) ) {
            $validation = $this->minimizer->validateCollection(
                $request->all(),
                $purpose,
            );

            if ( ! $validation->isValid ) {
                return response()->json( [
                    'error'   => 'data_minimization_violation',
                    'message' => 'Request contains prohibited or unnecessary data.',
                    'errors'  => $validation->errors,
                ], 422 );
            }

            // Filter request data to allowed fields only
            $filteredData = $this->minimizer->applyCollectionPolicy(
                $request->all(),
                $purpose,
            );

            $request->replace( $filteredData );
        }

        return $next( $request );
    }
}
