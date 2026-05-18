<?php

/**
 * Processing activity model — GDPR Article 30 record of processing activities.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProcessingActivity extends Model
{
    protected $table = 'processing_activities';

    protected $fillable = [
        'name',
        'description',
        'controller_name',
        'controller_contact',
        'processor_name',
        'processor_contact',
        'dpo_contact',
        'purposes',
        'legal_bases',
        'data_categories',
        'data_subjects',
        'recipients',
        'third_countries',
        'safeguards',
        'retention_policy',
        'security_measures',
        'automated_decisions',
        'dpia_required',
        'dpia_reference',
        'status',
        'suspension_reason',
        'suspended_at',
        'last_review_at',
        'next_review_at',
    ];

    public function scopeActive( Builder $query ): Builder
    {
        return $query->where( 'status', 'active' );
    }

    public function scopeRequiresDpia( Builder $query ): Builder
    {
        return $query->where( 'dpia_required', true );
    }

    protected function casts(): array
    {
        return [
            'purposes'            => 'array',
            'legal_bases'         => 'array',
            'data_categories'     => 'array',
            'data_subjects'       => 'array',
            'recipients'          => 'array',
            'third_countries'     => 'array',
            'safeguards'          => 'array',
            'retention_policy'    => 'array',
            'security_measures'   => 'array',
            'automated_decisions' => 'array',
            'dpia_required'       => 'boolean',
            'suspended_at'        => 'datetime',
            'last_review_at'      => 'datetime',
            'next_review_at'      => 'datetime',
        ];
    }
}
