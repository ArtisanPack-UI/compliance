<?php

/**
 * Data protection impact assessment model — Article 35 DPIA record.
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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataProtectionAssessment extends Model
{
    protected $table = 'data_protection_assessments';

    protected $fillable = [
        'assessment_number',
        'title',
        'description',
        'processing_activity_id',
        'status',
        'version',
        'parent_assessment_id',
        'data_categories',
        'data_subjects',
        'processing_purposes',
        'legal_bases',
        'recipients',
        'retention_periods',
        'transfers',
        'security_measures',
        'overall_risk_score',
        'overall_risk_level',
        'dpo_opinion',
        'dpo_reviewed_at',
        'dpo_reviewed_by',
        'created_by',
        'reviewed_by',
        'approved_at',
        'next_review_at',
    ];

    public function processingActivity(): BelongsTo
    {
        return $this->belongsTo( ProcessingActivity::class, 'processing_activity_id' );
    }

    public function parentAssessment(): BelongsTo
    {
        return $this->belongsTo( self::class, 'parent_assessment_id' );
    }

    public function risks(): HasMany
    {
        return $this->hasMany( AssessmentRisk::class, 'assessment_id' );
    }

    public function scopeApproved( Builder $query ): Builder
    {
        return $query->where( 'status', 'approved' );
    }

    public function scopeHighRisk( Builder $query ): Builder
    {
        return $query->whereIn( 'overall_risk_level', ['high', 'critical'] );
    }

    protected function casts(): array
    {
        return [
            'data_categories'     => 'array',
            'data_subjects'       => 'array',
            'processing_purposes' => 'array',
            'legal_bases'         => 'array',
            'recipients'          => 'array',
            'retention_periods'   => 'array',
            'transfers'           => 'array',
            'security_measures'   => 'array',
            'overall_risk_score'  => 'decimal:2',
            'dpo_reviewed_at'     => 'datetime',
            'approved_at'         => 'datetime',
            'next_review_at'      => 'datetime',
            'version'             => 'integer',
        ];
    }
}
