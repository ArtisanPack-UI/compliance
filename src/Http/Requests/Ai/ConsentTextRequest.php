<?php

/**
 * Form request for the consent-text suggestion AI endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class ConsentTextRequest extends FormRequest
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
            'purpose'              => [ 'required', 'string' ],
            'audience'             => [ 'required', 'string', 'max:255' ],
            'jurisdiction'         => [ 'required', 'string', 'max:32' ],
            'data_categories'      => [ 'required', 'array', 'min:1' ],
            'data_categories.*'    => [ 'required', 'string' ],
            'target_reading_level' => [ 'nullable', 'integer', 'between:6,14' ],
        ];
    }
}
