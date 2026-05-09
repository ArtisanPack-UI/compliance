<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Monitoring;

use ArtisanPackUI\Compliance\Compliance\Contracts\ComplianceCheckInterface;
use ArtisanPackUI\Compliance\Events\ComplianceCheckCompleted;
use ArtisanPackUI\Compliance\Events\ComplianceViolationDetected;
use ArtisanPackUI\Compliance\Models\ComplianceCheckResult;
use ArtisanPackUI\Compliance\Models\ComplianceScore;
use ArtisanPackUI\Compliance\Models\ComplianceViolation;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComplianceMonitor
{
    /**
     * @var array<string, ComplianceCheckInterface>
     */
    protected array $checks = [];

    /**
     * Register a compliance check.
     */
    public function registerCheck( ComplianceCheckInterface $check ): void
    {
        $this->checks[ $check->getName() ] = $check;
    }

    /**
     * Get all registered checks.
     *
     * @return array<string, ComplianceCheckInterface>
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    /**
     * Run a specific check.
     */
    public function runCheck( string $checkName ): ?ComplianceCheckResult
    {
        if ( ! isset( $this->checks[ $checkName ] ) ) {
            return null;
        }

        $check = $this->checks[ $checkName ];

        if ( ! $check->isEnabled() ) {
            return null;
        }

        $startTime = microtime( true );

        try {
            $result        = $check->run();
            $executionTime = (int) ( ( microtime( true ) - $startTime ) * 1000 );

            // Wrap result and violation creation in a transaction
            $storedResult = DB::transaction( function () use ( $checkName, $result, $executionTime, $check ) {
                // Store result
                $storedResult = ComplianceCheckResult::create( [
                    'check_name'        => $checkName,
                    'status'            => $result->status,
                    'score'             => $result->score,
                    'violations_found'  => count( $result->violations ),
                    'warnings_found'    => count( $result->warnings ),
                    'items_checked'     => $result->itemsChecked,
                    'items_compliant'   => $result->itemsCompliant,
                    'details'           => $result->details,
                    'execution_time_ms' => $executionTime,
                ] );

                // Create violations if any
                if ( ! empty( $result->violations ) ) {
                    $this->createViolations( $check, $result->violations );
                }

                return $storedResult;
            } );

            // Fire event only after transaction commits successfully
            event( new ComplianceCheckCompleted( $storedResult ) );

            return $storedResult;
        } catch ( Exception $e ) {
            Log::error( "Compliance check {$checkName} failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ] );

            return ComplianceCheckResult::create( [
                'check_name'        => $checkName,
                'status'            => 'error',
                'score'             => null,
                'violations_found'  => 0,
                'warnings_found'    => 0,
                'items_checked'     => 0,
                'items_compliant'   => 0,
                'details'           => ['error' => $e->getMessage()],
                'execution_time_ms' => (int) ( ( microtime( true ) - $startTime ) * 1000 ),
            ] );
        }
    }

    /**
     * Run all enabled checks.
     *
     * @return Collection<string, ComplianceCheckResult>
     */
    public function runAllChecks(): Collection
    {
        $results = collect();

        foreach ( $this->checks as $name => $check ) {
            if ( $check->isEnabled() ) {
                $result = $this->runCheck( $name );
                if ( $result ) {
                    $results->put( $name, $result );
                }
            }
        }

        // Calculate overall score
        $this->calculateOverallScore( $results );

        return $results;
    }

    /**
     * Run checks by category.
     *
     * @return Collection<string, ComplianceCheckResult>
     */
    public function runChecksByCategory( string $category ): Collection
    {
        $results = collect();

        foreach ( $this->checks as $name => $check ) {
            if ( $check->isEnabled() && $check->getCategory() === $category ) {
                $result = $this->runCheck( $name );
                if ( $result ) {
                    $results->put( $name, $result );
                }
            }
        }

        return $results;
    }

    /**
     * Get latest results for all checks.
     *
     * @return Collection<string, ComplianceCheckResult|null>
     */
    public function getLatestResults(): Collection
    {
        $results = collect();

        foreach ( $this->checks as $name => $check ) {
            $result = ComplianceCheckResult::getLatestForCheck( $name );
            $results->put( $name, $result );
        }

        return $results;
    }

    /**
     * Get check history.
     */
    public function getCheckHistory( string $checkName, int $limit = 30 ): Collection
    {
        return ComplianceCheckResult::forCheck( $checkName )
            ->orderByDesc( 'created_at' )
            ->limit( $limit )
            ->get();
    }

    /**
     * Get open violations.
     */
    public function getOpenViolations(): Collection
    {
        return ComplianceViolation::open()->orderByDesc( 'created_at' )->get();
    }

    /**
     * Get violations by severity.
     *
     * @return Collection<string, Collection<int, ComplianceViolation>>
     */
    public function getViolationsBySeverity(): Collection
    {
        return ComplianceViolation::open()
            ->orderByDesc( 'created_at' )
            ->get()
            ->groupBy( 'severity' );
    }

    /**
     * Get the current compliance score.
     */
    public function getCurrentScore(): ?ComplianceScore
    {
        return ComplianceScore::getLatest();
    }

    /**
     * Get compliance trend.
     *
     * @return Collection<int, ComplianceScore>
     */
    public function getScoreTrend( int $days = 30 ): Collection
    {
        return ComplianceScore::getHistory( null, $days );
    }

    /**
     * Get compliance status summary.
     *
     * @return array<string, mixed>
     */
    public function getStatusSummary(): array
    {
        $latestResults = $this->getLatestResults();
        $violations    = $this->getViolationsBySeverity();
        $score         = $this->getCurrentScore();

        return [
            'score'  => $score?->overall_score ?? 0,
            'grade'  => $score?->getGrade() ?? 'N/A',
            'checks' => [
                'total'   => count( $this->checks ),
                'passed'  => $latestResults->filter( fn ( $r ) => $r?->isPassed() )->count(),
                'failed'  => $latestResults->filter( fn ( $r ) => $r?->isFailed() )->count(),
                'pending' => $latestResults->filter( fn ( $r ) => null === $r )->count(),
            ],
            'violations' => [
                'total'    => ComplianceViolation::open()->count(),
                'critical' => $violations->get( 'critical', collect() )->count(),
                'high'     => $violations->get( 'high', collect() )->count(),
                'medium'   => $violations->get( 'medium', collect() )->count(),
                'low'      => $violations->get( 'low', collect() )->count(),
            ],
            'last_check_at' => $latestResults->max( fn ( $r ) => $r?->created_at )?->toIso8601String(),
        ];
    }

    /**
     * Calculate and store overall compliance score.
     *
     * @param  Collection<string, ComplianceCheckResult>  $results
     */
    protected function calculateOverallScore( Collection $results ): ComplianceScore
    {
        $categoryScores = [];
        $totalScore     = 0;
        $checkCount     = 0;

        // Calculate scores by category
        $byCategory = $results->groupBy( function ( $result ) {
            return $this->checks[ $result->check_name ]?->getCategory() ?? 'general';
        } );

        foreach ( $byCategory as $category => $categoryResults ) {
            $scores = $categoryResults->pluck( 'score' )->filter()->values();
            if ( $scores->isNotEmpty() ) {
                $categoryScores[ $category ] = $scores->avg();
                $totalScore += $categoryScores[ $category ];
                $checkCount++;
            }
        }

        $overallScore = $checkCount > 0 ? $totalScore / $checkCount : 0;

        // Generate findings and recommendations
        $findings        = [];
        $recommendations = [];

        foreach ( $results as $result ) {
            if ( 'failed' === $result->status ) {
                $check      = $this->checks[ $result->check_name ] ?? null;
                $findings[] = [
                    'check'      => $result->check_name,
                    'severity'   => $check?->getSeverity() ?? 'medium',
                    'violations' => $result->violations_found,
                ];
                if ( $check ) {
                    $recommendations[] = $check->getRemediation();
                }
            }
        }

        return ComplianceScore::create( [
            'overall_score'   => round( $overallScore, 2 ),
            'regulation'      => 'all',
            'category_scores' => $categoryScores,
            'findings'        => $findings,
            'recommendations' => array_unique( $recommendations ),
            'calculated_at'   => now(),
            'calculated_by'   => 'system',
        ] );
    }

    /**
     * Create violations from check results.
     *
     * Checks for existing open violations to prevent duplicates.
     *
     * @param  array<string>  $violations
     */
    protected function createViolations( ComplianceCheckInterface $check, array $violations ): void
    {
        foreach ( $violations as $violation ) {
            // Check for existing open violation with same check_name and title
            $existing = ComplianceViolation::where( 'check_name', $check->getName() )
                ->where( 'title', $violation )
                ->where( 'status', 'open' )
                ->first();

            if ( $existing ) {
                // Update the existing violation's timestamp to show it's still occurring
                $existing->touch();

                continue;
            }

            // Create new violation only if no matching open violation exists
            $created = ComplianceViolation::create( [
                'check_name'        => $check->getName(),
                'category'          => $check->getCategory(),
                'regulation'        => implode( ',', $check->getRegulations() ),
                'severity'          => $check->getSeverity(),
                'title'             => $violation,
                'description'       => $check->getDescription(),
                'remediation_steps' => [$check->getRemediation()],
                'status'            => 'open',
            ] );

            event( new ComplianceViolationDetected( $created));
        }
    }
}
