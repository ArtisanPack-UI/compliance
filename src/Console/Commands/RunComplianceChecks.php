<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Console\Commands;

use ArtisanPackUI\Compliance\Compliance\Monitoring\ComplianceMonitor;
use Illuminate\Console\Command;

class RunComplianceChecks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:check
                            {--check= : Run a specific check}
                            {--category= : Run checks for a specific category}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run compliance checks and generate violations';

    public function __construct( protected ComplianceMonitor $monitor )
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $checkName = $this->option( 'check' );
        $category  = $this->option( 'category' );

        if ( $checkName ) {
            $this->info( "Running compliance check: {$checkName}" );
            $result = $this->monitor->runCheck( $checkName );

            if ( ! $result ) {
                $this->error( "Check '{$checkName}' not found or disabled" );

                return 1;
            }

            $this->displayResult( $checkName, $result );

            return $result->isFailed() ? 1 : 0;
        }

        if ( $category ) {
            $this->info( "Running compliance checks for category: {$category}" );
            $results = $this->monitor->runChecksByCategory( $category );
        } else {
            $this->info( 'Running all compliance checks...' );
            $results = $this->monitor->runAllChecks();
        }

        $failed = 0;
        foreach ( $results as $name => $result ) {
            $this->displayResult( $name, $result );
            if ( $result->isFailed() ) {
                $failed++;
            }
        }

        $this->newLine();
        $score = $this->monitor->getCurrentScore();
        $this->info( "Overall Compliance Score: {$score?->overall_score}% (Grade: {$score?->getGrade()})" );

        if ( $failed > 0 ) {
            $this->error( "{$failed} check(s) failed" );

            return 1;
        }

        $this->info( 'All checks passed!' );

        return 0;
    }

    /**
     * Display check result.
     */
    protected function displayResult( string $name, $result ): void
    {
        $status = match ( $result->status ) {
            'passed'  => '<fg=green>PASSED</>',
            'failed'  => '<fg=red>FAILED</>',
            'warning' => '<fg=yellow>WARNING</>',
            default   => '<fg=gray>ERROR</>',
        };

        $score = null !== $result->score ? number_format( $result->score, 1 ) . '%' : 'N/A';

        $this->line( "  [{$status}] {$name} - Score: {$score}" );

        if ( $result->violations_found > 0 ) {
            $this->line( "    <fg=red>Violations: {$result->violations_found}</>" );
        }

        if ( $result->warnings_found > 0 ) {
            $this->line( "    <fg=yellow>Warnings: {$result->warnings_found}</>" );
        }
    }
}
