<?php

/**
 * Form request for the privacy-policy draft AI endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class PrivacyPolicyDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_name'                         => [ 'required', 'string', 'max:255' ],
            'contact_email'                             => [ 'required', 'email' ],
            'effective_date'                            => [ 'required', 'date_format:Y-m-d' ],
            'jurisdictions'                             => [ 'required', 'array', 'min:1' ],
            'jurisdictions.*'                           => [ 'required', 'string', 'max:32' ],
            'processing_activities'                     => [ 'required', 'array', 'min:1' ],
            'processing_activities.*.purpose'           => [ 'required', 'string' ],
            'processing_activities.*.legal_basis'       => [ 'required', 'string' ],
            'processing_activities.*.retention'         => [ 'required', 'string' ],
            'processing_activities.*.data_categories'   => [ 'required', 'array', 'min:1' ],
            'processing_activities.*.data_categories.*' => [ 'required', 'string' ],
            'processing_activities.*.recipients'        => [ 'nullable', 'array' ],
            'processing_activities.*.recipients.*'      => [ 'string' ],
        ];
    }
}
