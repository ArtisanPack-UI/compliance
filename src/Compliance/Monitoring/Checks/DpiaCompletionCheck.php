<?php

/**
 * DpiaCompletionCheck component of the Compliance package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Monitoring\Checks;

use ArtisanPackUI\Compliance\Compliance\Monitoring\CheckResult;
use ArtisanPackUI\Compliance\Models\DataProtectionAssessment;
use ArtisanPackUI\Compliance\Models\ProcessingActivity;

class DpiaCompletionCheck extends BaseComplianceCheck
{
    protected string $name = 'dpia_completion';

    protected string $description = 'Checks that DPIAs are completed for high-risk processing activities';

    protected string $category = 'assessment';

    protected array $regulations = ['gdpr'];

    protected string $severity = 'medium';

    protected string $remediation = 'Complete DPIA for all processing activities marked as requiring assessment. Ensure DPIAs are reviewed annually.';

    /**
     * Run the check.
     */
    public function run(): CheckResult
    {
        $violations = [];
        $warnings   = [];

        // Get activities requiring DPIA
        $activitiesRequiringDpia = ProcessingActivity::active()
            ->requiresDpia()
            ->get();

        $checked   = $activitiesRequiringDpia->count();
        $compliant = 0;

        foreach ( $activitiesRequiringDpia as $activity ) {
            // Check if DPIA exists
            $dpia = DataProtectionAssessment::where( 'processing_activity_id', $activity->id )
                ->approved()
                ->orderByDesc( 'version' )
                ->first();

            if ( ! $dpia ) {
                $violations[] = "Processing activity '{$activity->name}' requires DPIA but none exists";

                continue;
            }

            // Check if DPIA is due for review
            if ( $dpia->next_review_at && $dpia->next_review_at->isPast() ) {
                $warnings[] = "DPIA for '{$activity->name}' is overdue for review";
            }

            // Check for unaddressed high risks
            $highRisks = $dpia->risks()
                ->highRisk()
                ->whereNotIn( 'status', ['mitigated', 'accepted'] )
                ->count();

            if ( $highRisks > 0 ) {
                $warnings[] = "DPIA for '{$activity->name}' has {$highRisks} unaddressed high/critical risks";
            }

            $compliant++;
        }

        // Also check for DPIAs without linked activities
        $orphanDpias = DataProtectionAssessment::whereNull( 'processing_activity_id' )->count();
        if ( $orphanDpias > 0 ) {
            $warnings[] = "{$orphanDpias} DPIA(s) not linked to processing activities";
        }

        $details = [
            'activities_requiring_dpia' => $checked,
            'dpia_reviews_due'          => DataProtectionAssessment::approved()
                ->where( 'next_review_at', '<=', now() )
                ->count(),
        ];

        if ( ! empty( $violations ) ) {
            return $this->failed( $violations, $checked, $compliant, array_merge( $details, ['warnings' => $warnings] ) );
        }

        if ( ! empty( $warnings ) ) {
            return $this->warning( $warnings, $checked, $compliant, $details );
        }

        return $this->passed( $checked, $compliant, $details );
    }
}
