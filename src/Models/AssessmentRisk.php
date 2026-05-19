<?php

/**
 * Assessment risk model — single risk identified during a DPIA, with likelihood + impact scoring.
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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentRisk extends Model
{
    protected $table = 'assessment_risks';

    protected $fillable = [
        'assessment_id',
        'risk_category',
        'risk_title',
        'risk_description',
        'likelihood',
        'impact',
        'inherent_score',
        'residual_score',
        'risk_level',
        'risk_owner',
        'status',
        'accepted_by',
        'accepted_at',
        'acceptance_justification',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo( DataProtectionAssessment::class, 'assessment_id' );
    }

    public function mitigations(): HasMany
    {
        return $this->hasMany( RiskMitigation::class, 'risk_id' );
    }

    public function calculateInherentScore(): float
    {
        $likelihoodScore = match ( $this->likelihood ) {
            'rare'           => 1.0,
            'unlikely'       => 2.0,
            'possible'       => 3.0,
            'likely'         => 4.0,
            'almost_certain' => 5.0,
            default          => 0.0,
        };

        $impactScore = match ( $this->impact ) {
            'negligible' => 1.0,
            'minor'      => 2.0,
            'moderate'   => 3.0,
            'major'      => 4.0,
            'severe'     => 5.0,
            default      => 0.0,
        };

        return $likelihoodScore * $impactScore;
    }

    public static function determineRiskLevel( float $score ): string
    {
        return match ( true ) {
            $score >= 20.0 => 'critical',
            $score >= 12.0 => 'high',
            $score >= 6.0  => 'medium',
            default        => 'low',
        };
    }

    protected function casts(): array
    {
        return [
            'inherent_score' => 'decimal:2',
            'residual_score' => 'decimal:2',
            'accepted_at'    => 'datetime',
        ];
    }
}
