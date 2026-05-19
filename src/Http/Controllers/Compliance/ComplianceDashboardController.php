<?php

/**
 * ComplianceDashboardController HTTP controller.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Http\Controllers\Compliance;

use ArtisanPackUI\Compliance\Compliance\Monitoring\ComplianceMonitor;
use ArtisanPackUI\Compliance\Compliance\Reporting\ReportGenerator;
use ArtisanPackUI\Compliance\Models\ComplianceCheckResult;
use ArtisanPackUI\Compliance\Models\ComplianceScore;
use ArtisanPackUI\Compliance\Models\ComplianceViolation;
use ArtisanPackUI\Compliance\Models\ConsentPolicy;
use ArtisanPackUI\Compliance\Models\ConsentRecord;
use ArtisanPackUI\Compliance\Models\DataProtectionAssessment;
use ArtisanPackUI\Compliance\Models\ErasureRequest;
use ArtisanPackUI\Compliance\Models\PortabilityRequest;
use ArtisanPackUI\Compliance\Models\ScheduledComplianceReport;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComplianceDashboardController extends Controller
{
    public function __construct(
        protected ComplianceMonitor $monitor,
        protected ReportGenerator $reportGenerator,
    ) {
    }

    /**
     * Display the compliance dashboard overview.
     */
    public function index( Request $request ): JsonResponse
    {
        // Get overall compliance score
        $latestScore = ComplianceScore::latest( 'calculated_at' )->first();

        // Get violation statistics
        $violationStats = [
            'total'    => ComplianceViolation::count(),
            'open'     => ComplianceViolation::open()->count(),
            'critical' => ComplianceViolation::open()->where( 'severity', 'critical' )->count(),
            'high'     => ComplianceViolation::open()->where( 'severity', 'high' )->count(),
            'medium'   => ComplianceViolation::open()->where( 'severity', 'medium' )->count(),
            'low'      => ComplianceViolation::open()->where( 'severity', 'low' )->count(),
        ];

        // Get DSR statistics
        $dsrStats = [
            'erasure' => [
                'pending'       => ErasureRequest::where( 'status', 'pending' )->count(),
                'processing'    => ErasureRequest::where( 'status', 'processing' )->count(),
                'completed_30d' => ErasureRequest::where( 'status', 'completed' )
                    ->where( 'completed_at', '>=', now()->subDays( 30 ) )
                    ->count(),
            ],
            'portability' => [
                'pending'       => PortabilityRequest::where( 'status', 'pending' )->count(),
                'processing'    => PortabilityRequest::where( 'status', 'processing' )->count(),
                'completed_30d' => PortabilityRequest::where( 'status', 'completed' )
                    ->where( 'completed_at', '>=', now()->subDays( 30 ) )
                    ->count(),
            ],
        ];

        // Get consent statistics
        $consentStats = [
            'active_policies' => ConsentPolicy::active()->count(),
            'total_consents'  => ConsentRecord::where( 'status', 'granted' )->count(),
            'consent_rate'    => $this->calculateConsentRate(),
        ];

        // Get DPIA statistics
        $dpiaStats = [
            'total'     => DataProtectionAssessment::count(),
            'pending'   => DataProtectionAssessment::where( 'status', 'pending' )->count(),
            'in_review' => DataProtectionAssessment::where( 'status', 'in_review' )->count(),
            'high_risk' => DataProtectionAssessment::where( 'overall_risk_level', 'high' )->count(),
        ];

        // Get recent check results
        $recentChecks = ComplianceCheckResult::with( 'violation' )
            ->orderBy( 'checked_at', 'desc' )
            ->limit( 10 )
            ->get()
            ->map( fn ( $check ) => [
                'check_name' => $check->check_name,
                'category'   => $check->category,
                'passed'     => $check->passed,
                'checked_at' => $check->checked_at->toIso8601String(),
            ] );

        return response()->json( [
            'success' => true,
            'data'    => [
                'compliance_score' => $latestScore ? [
                    'overall'         => $latestScore->overall_score,
                    'category_scores' => $latestScore->category_scores,
                    'calculated_at'   => $latestScore->calculated_at->toIso8601String(),
                ] : null,
                'violations'    => $violationStats,
                'dsr'           => $dsrStats,
                'consent'       => $consentStats,
                'dpia'          => $dpiaStats,
                'recent_checks' => $recentChecks,
            ],
        ] );
    }

    /**
     * List all compliance violations.
     */
    public function violations( Request $request ): JsonResponse
    {
        $query = ComplianceViolation::query();

        // Filter by status
        if ( $request->has( 'status' ) ) {
            $query->where( 'status', $request->input( 'status' ) );
        }

        // Filter by severity
        if ( $request->has( 'severity' ) ) {
            $query->where( 'severity', $request->input( 'severity' ) );
        }

        // Filter by category
        if ( $request->has( 'category' ) ) {
            $query->where( 'category', $request->input( 'category' ) );
        }

        $violations = $query->orderBy( 'created_at', 'desc' )
            ->paginate( max( 1, min( (int) $request->input( 'per_page', 20 ), 100 ) ) );

        return response()->json( [
            'success' => true,
            'data'    => [
                'violations' => $violations->through( fn ( $v ) => [
                    'id'                   => $v->id,
                    'violation_number'     => $v->violation_number,
                    'title'                => $v->title,
                    'severity'             => $v->severity,
                    'category'             => $v->category,
                    'status'               => $v->status,
                    'remediation_deadline' => $v->remediation_deadline?->toIso8601String(),
                    'created_at'           => $v->created_at->toIso8601String(),
                ] ),
                'pagination' => [
                    'current_page' => $violations->currentPage(),
                    'last_page'    => $violations->lastPage(),
                    'per_page'     => $violations->perPage(),
                    'total'        => $violations->total(),
                ],
            ],
        ] );
    }

    /**
     * Show a specific violation.
     */
    public function showViolation( ComplianceViolation $violation ): JsonResponse
    {
        return response()->json( [
            'success' => true,
            'data'    => [
                'violation' => [
                    'id'                   => $violation->id,
                    'violation_number'     => $violation->violation_number,
                    'title'                => $violation->title,
                    'description'          => $violation->description,
                    'severity'             => $violation->severity,
                    'category'             => $violation->category,
                    'status'               => $violation->status,
                    'source'               => $violation->source,
                    'affected_data'        => $violation->affected_data,
                    'remediation_steps'    => $violation->remediation_steps,
                    'remediation_deadline' => $violation->remediation_deadline?->toIso8601String(),
                    'resolved_at'          => $violation->resolved_at?->toIso8601String(),
                    'resolution_notes'     => $violation->resolution_notes,
                    'created_at'           => $violation->created_at->toIso8601String(),
                ],
            ],
        ] );
    }

    /**
     * Resolve a violation.
     */
    public function resolveViolation( Request $request, ComplianceViolation $violation ): JsonResponse
    {
        $validated = $request->validate( [
            'resolution_notes'     => 'required|string|max:2000',
            'remediation_evidence' => 'nullable|array',
        ] );

        if ( 'resolved' === $violation->status ) {
            return response()->json( [
                'success' => false,
                'message' => 'This violation is already resolved.',
            ], 422 );
        }

        $violation->update( [
            'status'               => 'resolved',
            'resolved_at'          => now(),
            'resolved_by'          => $request->user()?->id,
            'resolution_notes'     => $validated['resolution_notes'],
            'remediation_evidence' => $validated['remediation_evidence'] ?? null,
        ] );

        return response()->json( [
            'success' => true,
            'message' => 'Violation marked as resolved.',
            'data'    => [
                'violation' => [
                    'violation_number' => $violation->violation_number,
                    'status'           => $violation->status,
                    'resolved_at'      => $violation->resolved_at->toIso8601String(),
                ],
            ],
        ] );
    }

    /**
     * List all DSR requests (erasure and portability).
     */
    public function dsrRequests( Request $request ): JsonResponse
    {
        $type = $request->input( 'type', 'all' );

        $data = [];

        if ( 'all' === $type || 'erasure' === $type ) {
            $erasureQuery = ErasureRequest::with( 'user:id,name,email' );

            if ( $request->has( 'status' ) ) {
                $erasureQuery->where( 'status', $request->input( 'status' ) );
            }

            $data['erasure_requests'] = $erasureQuery
                ->orderBy( 'created_at', 'desc' )
                ->limit( 50 )
                ->get()
                ->map( fn ( $r ) => [
                    'id'             => $r->id,
                    'request_number' => $r->request_number,
                    'user'           => $r->user ? [
                        'id'    => $r->user->id,
                        'name'  => $r->user->name,
                        'email' => $r->user->email,
                    ] : null,
                    'status'       => $r->status,
                    'scope'        => $r->scope,
                    'scheduled_at' => $r->scheduled_at?->toIso8601String(),
                    'created_at'   => $r->created_at->toIso8601String(),
                ] );
        }

        if ( 'all' === $type || 'portability' === $type ) {
            $portabilityQuery = PortabilityRequest::with( 'user:id,name,email' );

            if ( $request->has( 'status' ) ) {
                $portabilityQuery->where( 'status', $request->input( 'status' ) );
            }

            $data['portability_requests'] = $portabilityQuery
                ->orderBy( 'created_at', 'desc' )
                ->limit( 50 )
                ->get()
                ->map( fn ( $r ) => [
                    'id'             => $r->id,
                    'request_number' => $r->request_number,
                    'user'           => $r->user ? [
                        'id'    => $r->user->id,
                        'name'  => $r->user->name,
                        'email' => $r->user->email,
                    ] : null,
                    'status'     => $r->status,
                    'format'     => $r->format,
                    'created_at' => $r->created_at->toIso8601String(),
                ] );
        }

        return response()->json( [
            'success' => true,
            'data'    => $data,
        ] );
    }

    /**
     * Get consent overview.
     */
    public function consentOverview( Request $request ): JsonResponse
    {
        $policies = ConsentPolicy::withCount( [
            'consentRecords as granted_count'   => fn ( $q ) => $q->where( 'status', 'granted' ),
            'consentRecords as withdrawn_count' => fn ( $q ) => $q->where( 'status', 'withdrawn' ),
        ] )->get();

        $consentTrend = ConsentRecord::selectRaw( 'DATE(granted_at) as date, COUNT(*) as count' )
            ->where( 'status', 'granted' )
            ->where( 'granted_at', '>=', now()->subDays( 30 ) )
            ->groupBy( 'date' )
            ->orderBy( 'date' )
            ->get();

        return response()->json( [
            'success' => true,
            'data'    => [
                'policies' => $policies->map( fn ( $p ) => [
                    'id'              => $p->id,
                    'name'            => $p->name,
                    'slug'            => $p->slug,
                    'version'         => $p->version,
                    'is_required'     => $p->is_required,
                    'granted_count'   => $p->granted_count,
                    'withdrawn_count' => $p->withdrawn_count,
                ] ),
                'consent_trend' => $consentTrend->map( fn ( $t ) => [
                    'date'  => $t->date,
                    'count' => $t->count,
                ] ),
            ],
        ] );
    }

    /**
     * Get DPIA overview.
     */
    public function dpiaOverview( Request $request ): JsonResponse
    {
        $assessments = DataProtectionAssessment::with( 'processingActivity' )
            ->orderBy( 'created_at', 'desc' )
            ->paginate( max( 1, min( (int) $request->input( 'per_page', 20 ), 100 ) ) );

        return response()->json( [
            'success' => true,
            'data'    => [
                'assessments' => $assessments->through( fn ( $a ) => [
                    'id'                  => $a->id,
                    'title'               => $a->title,
                    'processing_activity' => $a->processingActivity?->name,
                    'status'              => $a->status,
                    'risk_level'          => $a->overall_risk_level,
                    'overall_risk_score'  => $a->overall_risk_score,
                    'assessor_name'       => $a->assessor_name,
                    'dpo_review_required' => $a->dpo_review_required,
                    'created_at'          => $a->created_at->toIso8601String(),
                ] ),
                'pagination' => [
                    'current_page' => $assessments->currentPage(),
                    'last_page'    => $assessments->lastPage(),
                    'per_page'     => $assessments->perPage(),
                    'total'        => $assessments->total(),
                ],
            ],
        ] );
    }

    /**
     * List compliance reports.
     */
    public function reports( Request $request ): JsonResponse
    {
        $reports = ScheduledComplianceReport::orderBy( 'created_at', 'desc' )
            ->paginate( max( 1, min( (int) $request->input( 'per_page', 20 ), 100 ) ) );

        return response()->json( [
            'success' => true,
            'data'    => [
                'reports' => $reports->through( fn ( $r ) => [
                    'id'                => $r->id,
                    'name'              => $r->name,
                    'type'              => $r->type,
                    'frequency'         => $r->frequency,
                    'is_active'         => $r->is_active,
                    'last_generated_at' => $r->last_generated_at?->toIso8601String(),
                    'next_scheduled_at' => $r->next_scheduled_at?->toIso8601String(),
                    'created_at'        => $r->created_at->toIso8601String(),
                ] ),
                'pagination' => [
                    'current_page' => $reports->currentPage(),
                    'last_page'    => $reports->lastPage(),
                    'per_page'     => $reports->perPage(),
                    'total'        => $reports->total(),
                ],
            ],
        ] );
    }

    /**
     * Generate a compliance report.
     */
    public function generateReport( Request $request ): JsonResponse
    {
        $validated = $request->validate( [
            'type'   => 'required|in:compliance_status,consent_audit,dsr_summary,dpia_summary',
            'period' => 'required|in:daily,weekly,monthly,quarterly,annual',
            'format' => 'required|in:pdf,xlsx,json',
        ] );

        try {
            $report = $this->reportGenerator->generate(
                $validated['type'],
                $validated['period'],
                $validated['format'],
            );

            return response()->json( [
                'success' => true,
                'message' => 'Report generated successfully.',
                'data'    => [
                    'report' => [
                        'title'        => $report->title,
                        'type'         => $report->type,
                        'format'       => $report->format,
                        'file_path'    => $report->filePath,
                        'generated_at' => $report->generatedAt->toIso8601String(),
                    ],
                ],
            ] );
        } catch ( Exception $e ) {
            // Generate a correlation ID for support reference
            $errorId = Str::uuid()->toString();

            // Log the full exception with context
            Log::error( 'Failed to generate compliance report', [
                'error_id' => $errorId,
                'type'     => $validated['type'],
                'period'   => $validated['period'],
                'format'   => $validated['format'],
                'user_id'  => $request->user()?->id,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ] );

            return response()->json( [
                'success'  => false,
                'message'  => 'Failed to generate report. Please try again or contact support.',
                'error_id' => $errorId,
            ], 500 );
        }
    }

    /**
     * Download a generated report.
     */
    public function downloadReport( Request $request, ScheduledComplianceReport $report )
    {
        if ( ! $report->last_file_path ) {
            return response()->json( [
                'success' => false,
                'message' => 'No report file available.',
            ], 404 );
        }

        $disk = config( 'artisanpack.compliance.reporting.storage_disk', 'local' );

        if ( ! Storage::disk( $disk )->exists( $report->last_file_path ) ) {
            return response()->json( [
                'success' => false,
                'message' => 'Report file not found.',
            ], 404 );
        }

        return Storage::disk( $disk )->download( $report->last_file_path );
    }

    /**
     * Calculate the overall consent rate.
     */
    protected function calculateConsentRate(): float
    {
        $totalPolicies = ConsentPolicy::active()->count();
        if ( 0 === $totalPolicies ) {
            return 0.0;
        }

        // This is a simplified calculation
        // In practice, you'd want to calculate based on unique users
        $grantedConsents = ConsentRecord::where( 'status', 'granted' )->count();
        $totalRecords    = ConsentRecord::count();

        if ( 0 === $totalRecords ) {
            return 0.0;
        }

        return round( ( $grantedConsents / $totalRecords) * 100, 2);
    }
}
