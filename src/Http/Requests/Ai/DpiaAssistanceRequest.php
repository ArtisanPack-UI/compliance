<?php

/**
 * Form request for the DPIA assistance AI endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class DpiaAssistanceRequest extends FormRequest
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
            'activity_name'          => [ 'required', 'string', 'max:255' ],
            'activity_purpose'       => [ 'required', 'string' ],
            'processing_scale'       => [ 'required', 'string', 'max:64' ],
            'data_categories'        => [ 'required', 'array', 'min:1' ],
            'data_categories.*'      => [ 'required', 'string' ],
            'data_subjects'          => [ 'required', 'array', 'min:1' ],
            'data_subjects.*'        => [ 'required', 'string' ],
            'systems_involved'       => [ 'nullable', 'array' ],
            'systems_involved.*'     => [ 'string' ],
            'transfers_outside_eea'  => [ 'nullable', 'boolean' ],
        ];
    }
}
