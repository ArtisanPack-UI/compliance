<?php

/**
 * RiskCalculator component of the Compliance package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Assessment;

use ArtisanPackUI\Compliance\Models\AssessmentRisk;
use Illuminate\Support\Collection;

class RiskCalculator
{
    /**
     * Likelihood values and their numeric scores.
     * Mirrors AssessmentRisk::LIKELIHOOD_SCORES for matrix calculations.
     *
     * @var array<string, int>
     */
    protected const LIKELIHOOD_SCORES = [
        'rare'           => 1,
        'unlikely'       => 2,
        'possible'       => 3,
        'likely'         => 4,
        'almost_certain' => 5,
    ];

    /**
     * Impact values and their numeric scores.
     * Mirrors AssessmentRisk::IMPACT_SCORES for matrix calculations.
     *
     * @var array<string, int>
     */
    protected const IMPACT_SCORES = [
        'negligible' => 1,
        'minor'      => 2,
        'moderate'   => 3,
        'major'      => 4,
        'severe'     => 5,
    ];

    /**
     * Risk level thresholds using inclusive lower bounds and exclusive upper bounds.
     * Ranges are contiguous: low [0, 4), medium [4, 9), high [9, 16), critical [16, 25]
     *
     * @var array<string, array{min: float, max: float}>
     */
    protected array $thresholds = [
        'low'      => ['min' => 0, 'max' => 4],
        'medium'   => ['min' => 4, 'max' => 9],
        'high'     => ['min' => 9, 'max' => 16],
        'critical' => ['min' => 16, 'max' => 25],
    ];

    /**
     * Calculate overall risk for a collection of risks.
     *
     * @param  Collection<int, AssessmentRisk>  $risks
     *
     * @return array{score: float, level: string}
     */
    public function calculateOverallRisk( Collection $risks ): array
    {
        if ( $risks->isEmpty() ) {
            return ['score' => 0, 'level' => 'low'];
        }

        // Use highest risk or average based on methodology
        $method = config( 'artisanpack.compliance.dpia.risk_calculation_method', 'highest' );

        $score = match ( $method ) {
            'highest'  => $risks->max( 'inherent_score' ),
            'average'  => $risks->avg( 'inherent_score' ),
            'weighted' => $this->calculateWeightedRisk( $risks ),
            default    => $risks->max( 'inherent_score' ),
        };

        return [
            'score' => round( $score, 2 ),
            'level' => $this->determineLevel( $score ),
        ];
    }

    /**
     * Calculate residual risk after mitigations.
     */
    public function calculateResidualRisk( AssessmentRisk $risk ): float
    {
        $mitigations = $risk->mitigations()->where( 'status', 'implemented' )->get();

        if ( $mitigations->isEmpty() ) {
            return $risk->inherent_score;
        }

        $effectivenessTotal   = $mitigations->sum( 'effectiveness_rating' );
        $averageEffectiveness = $effectivenessTotal / $mitigations->count();

        // Reduce inherent risk based on mitigation effectiveness (0-100%)
        $reduction = ( $averageEffectiveness / 100 ) * $risk->inherent_score;

        return max( 1, $risk->inherent_score - $reduction );
    }

    /**
     * Determine risk level from score.
     *
     * Uses inclusive lower bound and exclusive upper bound for all levels
     * except critical which uses inclusive upper bound.
     */
    public function determineLevel( float $score ): string
    {
        // Handle edge cases
        if ( $score < 0 ) {
            return 'low';
        }

        if ( $score >= 25 ) {
            return 'critical';
        }

        // Use >= min and < max for contiguous ranges (except critical which is <= max)
        foreach ( $this->thresholds as $level => $range ) {
            if ( 'critical' === $level ) {
                if ( $score >= $range['min'] && $score <= $range['max'] ) {
                    return $level;
                }
            } else {
                if ( $score >= $range['min'] && $score < $range['max'] ) {
                    return $level;
                }
            }
        }

        return 'critical';
    }

    /**
     * Suggest risk level based on category and data types.
     *
     * @param  array<string>  $dataCategories
     */
    public function suggestRiskLevel( string $riskCategory, array $dataCategories ): string
    {
        $specialCategories = config( 'artisanpack.compliance.special_categories', [] );

        $hasSpecialCategory = ! empty( array_intersect( $dataCategories, $specialCategories ) );

        $baseLevels = [
            'data_breach'         => $hasSpecialCategory ? 'critical' : 'high',
            'unauthorized_access' => $hasSpecialCategory ? 'high' : 'medium',
            'data_loss'           => $hasSpecialCategory ? 'high' : 'medium',
            'non_compliance'      => 'high',
            'reputational'        => 'medium',
            'operational'         => 'low',
        ];

        return $baseLevels[ $riskCategory ] ?? 'medium';
    }

    /**
     * Get risk matrix data for visualization.
     *
     * @return array<string, array<string, string>>
     */
    public function getRiskMatrix(): array
    {
        $likelihoods = array_keys( self::LIKELIHOOD_SCORES );
        $impacts     = array_keys( self::IMPACT_SCORES );

        $matrix = [];
        foreach ( $likelihoods as $likelihood ) {
            foreach ( $impacts as $impact ) {
                $likelihoodScore                  = self::LIKELIHOOD_SCORES[ $likelihood ];
                $impactScore                      = self::IMPACT_SCORES[ $impact ];
                $score                            = $likelihoodScore * $impactScore;
                $matrix[ $likelihood ][ $impact ] = $this->determineLevel( $score );
            }
        }

        return $matrix;
    }

    /**
     * Get recommended mitigations based on risk category.
     *
     * @return array<array{title: string, type: string, description: string}>
     */
    public function getRecommendedMitigations( string $riskCategory ): array
    {
        $recommendations = [
            'data_breach' => [
                ['title' => 'Implement encryption', 'type' => 'technical', 'description' => 'Encrypt data at rest and in transit'],
                ['title' => 'Access controls', 'type' => 'technical', 'description' => 'Implement role-based access control'],
                ['title' => 'Incident response plan', 'type' => 'organizational', 'description' => 'Document and test incident response procedures'],
            ],
            'unauthorized_access' => [
                ['title' => 'Multi-factor authentication', 'type' => 'technical', 'description' => 'Require MFA for sensitive operations'],
                ['title' => 'Access logging', 'type' => 'technical', 'description' => 'Implement comprehensive access logging'],
                ['title' => 'Regular access reviews', 'type' => 'organizational', 'description' => 'Periodically review and revoke unnecessary access'],
            ],
            'data_loss' => [
                ['title' => 'Backup procedures', 'type' => 'technical', 'description' => 'Implement regular encrypted backups'],
                ['title' => 'Disaster recovery', 'type' => 'organizational', 'description' => 'Document and test disaster recovery procedures'],
            ],
            'non_compliance' => [
                ['title' => 'Compliance training', 'type' => 'organizational', 'description' => 'Regular staff training on data protection'],
                ['title' => 'Data processing agreements', 'type' => 'contractual', 'description' => 'Ensure DPAs with all processors'],
                ['title' => 'Regular audits', 'type' => 'organizational', 'description' => 'Conduct periodic compliance audits'],
            ],
        ];

        return $recommendations[ $riskCategory ] ?? [];
    }

    /**
     * Calculate weighted risk score.
     *
     * @param  Collection<int, AssessmentRisk>  $risks
     */
    protected function calculateWeightedRisk( Collection $risks ): float
    {
        $weights = [
            'critical' => 4,
            'high'     => 3,
            'medium'   => 2,
            'low'      => 1,
        ];

        $totalWeight = 0;
        $weightedSum = 0;

        foreach ( $risks as $risk ) {
            $weight = $weights[ $risk->risk_level ] ?? 1;
            $weightedSum += $risk->inherent_score * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $weightedSum / $totalWeight : 0;
    }
}
