<?php

/**
 * ComplianceStatusReport component of the Compliance package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Reporting\Reports;

use ArtisanPackUI\Compliance\Compliance\Contracts\ReportTypeInterface;
use ArtisanPackUI\Compliance\Compliance\Monitoring\ComplianceMonitor;
use ArtisanPackUI\Compliance\Compliance\Reporting\ComplianceReport;
use ArtisanPackUI\Compliance\Models\ComplianceScore;
use ArtisanPackUI\Compliance\Models\ComplianceViolation;
use ArtisanPackUI\Compliance\Models\ErasureRequest;
use ArtisanPackUI\Compliance\Models\PortabilityRequest;

class ComplianceStatusReport implements ReportTypeInterface
{
    public function __construct( protected ComplianceMonitor $monitor )
    {
    }

    /**
     * Get report type name.
     */
    public function getName(): string
    {
        return 'compliance_status';
    }

    /**
     * Get report type description.
     */
    public function getDescription(): string
    {
        return 'Overall compliance status report including scores, violations, and DSR metrics';
    }

    /**
     * Get report category.
     */
    public function getCategory(): string
    {
        return 'compliance';
    }

    /**
     * Generate the report.
     *
     * @param  array<string, mixed>  $options
     */
    public function generate( array $options = [] ): ComplianceReport
    {
        $score      = ComplianceScore::getLatest();
        $violations = ComplianceViolation::open()->get();

        $sections = [
            'summary'               => $this->generateSummary( $score ),
            'compliance_score'      => $this->generateScoreSection( $score ),
            'violations'            => $this->generateViolationsSection( $violations ),
            'data_subject_requests' => $this->generateDsrSection(),
            'check_results'         => $this->generateCheckResultsSection(),
        ];

        return new ComplianceReport(
            type: $this->getName(),
            title: 'Compliance Status Report',
            sections: $sections,
            metadata: [
                'regulation'   => $options['regulation'] ?? 'all',
                'generated_by' => auth()->user()?->name ?? 'system',
            ],
        );
    }

    /**
     * Get available options.
     *
     * @return array<string, mixed>
     */
    public function getAvailableOptions(): array
    {
        return [
            'regulation' => [
                'type'    => 'select',
                'options' => ['all', 'gdpr', 'ccpa', 'lgpd'],
                'default' => 'all',
            ],
        ];
    }

    /**
     * Get supported formats.
     *
     * @return array<string>
     */
    public function getSupportedFormats(): array
    {
        return ['json', 'html', 'csv', 'pdf'];
    }

    /**
     * Get default schedule.
     */
    public function getDefaultSchedule(): ?string
    {
        return '0 8 * * 1'; // Weekly on Monday at 8am
    }

    /**
     * Generate summary section.
     *
     * @return array<string, mixed>
     */
    protected function generateSummary( ?ComplianceScore $score ): array
    {
        $violations = ComplianceViolation::open();

        return [
            'overall_score'       => $score?->overall_score ?? 'N/A',
            'grade'               => $score?->getGrade() ?? 'N/A',
            'status'              => $score && $score->isPassing() ? 'Compliant' : 'Non-Compliant',
            'open_violations'     => $violations->count(),
            'critical_violations' => $violations->where( 'severity', 'critical' )->count(),
            'report_period'       => now()->format( 'F Y' ),
        ];
    }

    /**
     * Generate score section.
     *
     * @return array<string, mixed>
     */
    protected function generateScoreSection( ?ComplianceScore $score ): array
    {
        return [
            'overall'         => $score?->overall_score ?? 0,
            'category_scores' => $score?->category_scores ?? [],
            'findings'        => $score?->findings ?? [],
            'recommendations' => $score?->recommendations ?? [],
            'calculated_at'   => $score?->calculated_at?->toIso8601String(),
        ];
    }

    /**
     * Generate violations section.
     *
     * @param  \Illuminate\Support\Collection<int, ComplianceViolation>  $violations
     *
     * @return array<string, mixed>
     */
    protected function generateViolationsSection( $violations ): array
    {
        return [
            'total_open'  => $violations->count(),
            'by_severity' => [
                'critical' => $violations->where( 'severity', 'critical' )->count(),
                'high'     => $violations->where( 'severity', 'high' )->count(),
                'medium'   => $violations->where( 'severity', 'medium' )->count(),
                'low'      => $violations->where( 'severity', 'low' )->count(),
            ],
            'by_category' => $violations->groupBy( 'category' )->map->count()->toArray(),
            'overdue'     => $violations->filter->isOverdue()->count(),
            'details'     => $violations->take( 10 )->map( fn ( $v ) => [
                'number'     => $v->violation_number,
                'title'      => $v->title,
                'severity'   => $v->severity,
                'status'     => $v->status,
                'created_at' => $v->created_at->toIso8601String(),
            ] )->toArray(),
        ];
    }

    /**
     * Generate DSR section.
     *
     * @return array<string, mixed>
     */
    protected function generateDsrSection(): array
    {
        return [
            'erasure_requests' => [
                'pending'              => ErasureRequest::pending()->count(),
                'completed_this_month' => ErasureRequest::where( 'status', 'completed' )
                    ->whereMonth( 'completed_at', now()->month )
                    ->count(),
                'overdue' => ErasureRequest::overdue()->count(),
            ],
            'portability_requests' => [
                'pending'              => PortabilityRequest::pending()->count(),
                'completed_this_month' => PortabilityRequest::where( 'status', 'completed' )
                    ->whereMonth( 'completed_at', now()->month )
                    ->count(),
            ],
        ];
    }

    /**
     * Generate check results section.
     *
     * @return array<string, mixed>
     */
    protected function generateCheckResultsSection(): array
    {
        $results = $this->monitor->getLatestResults();

        return [
            'total_checks' => $results->count(),
            'passed'       => $results->filter( fn ( $r ) => $r?->isPassed() )->count(),
            'failed'       => $results->filter( fn ( $r ) => $r?->isFailed() )->count(),
            'checks'       => $results->map( fn ( $r ) => $r ? [
                'name'     => $r->check_name,
                'status'   => $r->status,
                'score'    => $r->score,
                'last_run' => $r->created_at->toIso8601String(),
            ] : null )->filter()->values()->toArray(),
        ];
    }
}
