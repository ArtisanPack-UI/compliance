<?php

/**
 * DpiaService component of the Compliance package.
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
use ArtisanPackUI\Compliance\Models\DataProtectionAssessment;
use ArtisanPackUI\Compliance\Models\ProcessingActivity;
use ArtisanPackUI\Compliance\Models\RiskMitigation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DpiaService
{
    protected RiskCalculator $riskCalculator;

    public function __construct( RiskCalculator $riskCalculator )
    {
        $this->riskCalculator = $riskCalculator;
    }

    /**
     * Create a new DPIA.
     *
     * @param  array<string, mixed>  $data
     */
    public function create( array $data ): DataProtectionAssessment
    {
        return DataProtectionAssessment::create( [
            'title'                  => $data['title'],
            'description'            => $data['description'] ?? null,
            'processing_activity_id' => $data['processing_activity_id'] ?? null,
            'status'                 => 'draft',
            'version'                => 1,
            'data_categories'        => $data['data_categories'] ?? [],
            'data_subjects'          => $data['data_subjects'] ?? [],
            'processing_purposes'    => $data['processing_purposes'] ?? [],
            'legal_bases'            => $data['legal_bases'] ?? [],
            'recipients'             => $data['recipients'] ?? [],
            'retention_periods'      => $data['retention_periods'] ?? [],
            'transfers'              => $data['transfers'] ?? [],
            'security_measures'      => $data['security_measures'] ?? [],
            'created_by'             => auth()->id(),
        ] );
    }

    /**
     * Create revision of an existing DPIA.
     */
    public function createRevision( DataProtectionAssessment $assessment ): DataProtectionAssessment
    {
        return DB::transaction( function () use ( $assessment ) {
            $newVersion = $assessment->version + 1;

            return DataProtectionAssessment::create( [
                'title'                  => $assessment->title,
                'description'            => $assessment->description,
                'processing_activity_id' => $assessment->processing_activity_id,
                'status'                 => 'draft',
                'version'                => $newVersion,
                'parent_assessment_id'   => $assessment->id,
                'data_categories'        => $assessment->data_categories,
                'data_subjects'          => $assessment->data_subjects,
                'processing_purposes'    => $assessment->processing_purposes,
                'legal_bases'            => $assessment->legal_bases,
                'recipients'             => $assessment->recipients,
                'retention_periods'      => $assessment->retention_periods,
                'transfers'              => $assessment->transfers,
                'security_measures'      => $assessment->security_measures,
                'created_by'             => auth()->id(),
            ] );
        } );
    }

    /**
     * Add a risk to the assessment.
     *
     * @param  array<string, mixed>  $data
     */
    public function addRisk( DataProtectionAssessment $assessment, array $data ): AssessmentRisk
    {
        $risk = new AssessmentRisk( [
            'assessment_id'    => $assessment->id,
            'risk_category'    => $data['risk_category'],
            'risk_title'       => $data['risk_title'],
            'risk_description' => $data['risk_description'] ?? null,
            'likelihood'       => $data['likelihood'],
            'impact'           => $data['impact'],
            'risk_owner'       => $data['risk_owner'] ?? null,
            'status'           => 'identified',
        ] );

        // Calculate scores
        $risk->inherent_score = $risk->calculateInherentScore();
        $risk->risk_level     = AssessmentRisk::determineRiskLevel( $risk->inherent_score );
        $risk->save();

        // Recalculate overall risk
        $this->recalculateOverallRisk( $assessment );

        return $risk;
    }

    /**
     * Add a mitigation to a risk.
     *
     * @param  array<string, mixed>  $data
     */
    public function addMitigation( AssessmentRisk $risk, array $data ): RiskMitigation
    {
        return RiskMitigation::create( [
            'risk_id'     => $risk->id,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'type'        => $data['type'],
            'status'      => 'planned',
            'priority'    => $data['priority'] ?? 'medium',
            'assigned_to' => $data['assigned_to'] ?? null,
            'due_date'    => $data['due_date'] ?? null,
        ] );
    }

    /**
     * Submit assessment for review.
     */
    public function submitForReview( DataProtectionAssessment $assessment ): DataProtectionAssessment
    {
        $assessment->update( [
            'status' => 'in_review',
        ] );

        return $assessment;
    }

    /**
     * Record DPO review.
     */
    public function recordDpoReview( DataProtectionAssessment $assessment, string $opinion ): DataProtectionAssessment
    {
        $assessment->update( [
            'dpo_opinion'     => $opinion,
            'dpo_reviewed_at' => now(),
            'dpo_reviewed_by' => auth()->id(),
        ] );

        return $assessment;
    }

    /**
     * Approve the assessment.
     */
    public function approve( DataProtectionAssessment $assessment ): DataProtectionAssessment
    {
        $assessment->update( [
            'status'         => 'approved',
            'approved_at'    => now(),
            'reviewed_by'    => auth()->id(),
            'next_review_at' => now()->addYear(),
        ] );

        return $assessment;
    }

    /**
     * Reject the assessment.
     */
    public function reject( DataProtectionAssessment $assessment, string $reason ): DataProtectionAssessment
    {
        $assessment->update( [
            'status'      => 'rejected',
            'dpo_opinion' => $reason,
            'reviewed_by' => auth()->id(),
        ] );

        return $assessment;
    }

    /**
     * Request revision.
     */
    public function requestRevision( DataProtectionAssessment $assessment, string $reason ): DataProtectionAssessment
    {
        $assessment->update( [
            'status'      => 'revision_required',
            'dpo_opinion' => $reason,
            'reviewed_by' => auth()->id(),
        ] );

        return $assessment;
    }

    /**
     * Check if processing activity requires DPIA.
     */
    public function isRequired( ProcessingActivity $activity ): bool
    {
        // Check for special categories
        $specialCategories = config( 'artisanpack.compliance.special_categories', [] );
        $dataCategories    = $activity->data_categories ?? [];

        foreach ( $dataCategories as $category ) {
            if ( in_array( $category, $specialCategories ) ) {
                return true;
            }
        }

        // Check for large scale processing
        $subjects = $activity->data_subjects ?? [];
        if ( in_array( 'large_scale', $subjects ) ) {
            return true;
        }

        // Check for systematic monitoring
        $purposes = $activity->purposes ?? [];
        if ( in_array( 'systematic_monitoring', $purposes ) ) {
            return true;
        }

        // Check for automated decision making
        if ( ! empty( $activity->automated_decisions ) ) {
            return true;
        }

        return false;
    }

    /**
     * Get assessments due for review.
     */
    public function getDueForReview(): Collection
    {
        return DataProtectionAssessment::approved()
            ->where( 'next_review_at', '<=', now() )
            ->get();
    }

    /**
     * Get high risk assessments.
     */
    public function getHighRisk(): Collection
    {
        return DataProtectionAssessment::highRisk()->get();
    }

    /**
     * Generate assessment summary.
     *
     * @return array<string, mixed>
     */
    public function generateSummary( DataProtectionAssessment $assessment ): array
    {
        $risks = $assessment->risks;

        return [
            'assessment' => [
                'number'             => $assessment->assessment_number,
                'title'              => $assessment->title,
                'status'             => $assessment->status,
                'version'            => $assessment->version,
                'overall_risk_level' => $assessment->overall_risk_level,
                'overall_risk_score' => $assessment->overall_risk_score,
            ],
            'risks' => [
                'total'     => $risks->count(),
                'by_level'  => $risks->groupBy( 'risk_level' )->map->count(),
                'mitigated' => $risks->where( 'status', 'mitigated' )->count(),
                'accepted'  => $risks->where( 'status', 'accepted' )->count(),
            ],
            'mitigations' => [
                'total'       => $risks->flatMap->mitigations->count(),
                'implemented' => $risks->flatMap->mitigations->where( 'status', 'implemented' )->count(),
                'overdue'     => $risks->flatMap->mitigations->filter->isOverdue()->count(),
            ],
            'processing' => [
                'categories' => count( $assessment->data_categories ?? [] ),
                'subjects'   => count( $assessment->data_subjects ?? [] ),
                'purposes'   => count( $assessment->processing_purposes ?? [] ),
            ],
        ];
    }

    /**
     * Recalculate overall risk score.
     */
    protected function recalculateOverallRisk( DataProtectionAssessment $assessment ): void
    {
        $assessment->refresh();
        $result = $this->riskCalculator->calculateOverallRisk( $assessment->risks );

        $assessment->update( [
            'overall_risk_score' => $result['score'],
            'overall_risk_level' => $result['level'],
        ]);
    }
}
