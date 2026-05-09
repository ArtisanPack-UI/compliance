<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance;

use ArtisanPackUI\Compliance\Compliance\Assessment\DpiaService;
use ArtisanPackUI\Compliance\Compliance\Assessment\ProcessingActivityService;
use ArtisanPackUI\Compliance\Compliance\Assessment\RiskCalculator;
use ArtisanPackUI\Compliance\Compliance\Consent\ConsentManager;
use ArtisanPackUI\Compliance\Compliance\Consent\ConsentPolicyService;
use ArtisanPackUI\Compliance\Compliance\Consent\CookieConsentHandler;
use ArtisanPackUI\Compliance\Compliance\Erasure\ErasureService;
use ArtisanPackUI\Compliance\Compliance\Middleware\CheckConsentMiddleware;
use ArtisanPackUI\Compliance\Compliance\Middleware\DataMinimizationMiddleware;
use ArtisanPackUI\Compliance\Compliance\Minimization\AnonymizationEngine;
use ArtisanPackUI\Compliance\Compliance\Minimization\DataMinimizerService;
use ArtisanPackUI\Compliance\Compliance\Minimization\PseudonymizationEngine;
use ArtisanPackUI\Compliance\Compliance\Monitoring\ComplianceMonitor;
use ArtisanPackUI\Compliance\Compliance\Portability\PortabilityService;
use ArtisanPackUI\Compliance\Compliance\Reporting\ReportGenerator as ComplianceReportGenerator;
use ArtisanPackUI\Compliance\Console\Commands\GenerateComplianceReport;
use ArtisanPackUI\Compliance\Console\Commands\ProcessErasureRequests;
use ArtisanPackUI\Compliance\Console\Commands\ProcessPortabilityRequests;
use ArtisanPackUI\Compliance\Console\Commands\PurgeExpiredData;
use ArtisanPackUI\Compliance\Console\Commands\RunComplianceChecks;
use ArtisanPackUI\Compliance\Events\ComplianceCheckCompleted;
use ArtisanPackUI\Compliance\Events\ComplianceViolationDetected;
use ArtisanPackUI\Compliance\Events\ConsentGranted;
use ArtisanPackUI\Compliance\Events\ConsentWithdrawn;
use ArtisanPackUI\Compliance\Events\DataExportCompleted;
use ArtisanPackUI\Compliance\Events\DataExportRequested;
use ArtisanPackUI\Compliance\Events\ErasureCompleted;
use ArtisanPackUI\Compliance\Events\ErasureRequested;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Compliance package.
 *
 * Wires consent management, data subject rights (erasure + portability),
 * DPIA + processing activities, data minimization, monitoring, and
 * reporting. Loads config, migrations, routes, middleware aliases,
 * compliance event listeners, the dashboard gate, and console commands.
 */
class ComplianceServiceProvider extends ServiceProvider
{
    /**
     * Register container bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/artisanpack/compliance.php',
            'artisanpack.compliance',
        );

        $this->app->singleton( 'compliance', fn () => new Compliance() );

        $this->registerComplianceServices();
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->publishes( [
            __DIR__ . '/../config/artisanpack/compliance.php'
                => config_path( 'artisanpack/compliance.php' ),
        ], 'compliance-config' );

        if ( ! config( 'artisanpack.compliance.enabled', true ) ) {
            return;
        }

        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

        $this->registerComplianceMiddleware();

        if ( $this->app->runningInConsole() ) {
            $this->commands( [
                RunComplianceChecks::class,
                ProcessErasureRequests::class,
                ProcessPortabilityRequests::class,
                PurgeExpiredData::class,
                GenerateComplianceReport::class,
            ] );
        }

        $this->registerComplianceEventListeners();

        if ( config( 'artisanpack.compliance.routes.enabled', true ) ) {
            $this->loadRoutesFrom( __DIR__ . '/../routes/compliance.php' );
        }

        $this->registerComplianceDashboardGate();
    }

    /**
     * Register compliance service singletons.
     */
    protected function registerComplianceServices(): void
    {
        // Consent management
        $this->app->singleton( ConsentManager::class, fn () => new ConsentManager() );
        $this->app->singleton( ConsentPolicyService::class, fn () => new ConsentPolicyService() );
        $this->app->singleton( CookieConsentHandler::class, fn () => new CookieConsentHandler() );

        // Data minimization
        $this->app->singleton( AnonymizationEngine::class, fn () => new AnonymizationEngine() );
        $this->app->singleton( PseudonymizationEngine::class, fn () => new PseudonymizationEngine() );
        $this->app->singleton( DataMinimizerService::class, function ( $app ): DataMinimizerService {
            return new DataMinimizerService(
                $app->make( AnonymizationEngine::class ),
                $app->make( PseudonymizationEngine::class ),
            );
        } );

        // Data subject rights
        $this->app->singleton( ErasureService::class, fn () => new ErasureService() );
        $this->app->singleton( PortabilityService::class, fn () => new PortabilityService() );

        // DPIA / processing activities
        $this->app->singleton( RiskCalculator::class, fn () => new RiskCalculator() );
        $this->app->singleton( ProcessingActivityService::class, fn () => new ProcessingActivityService() );
        $this->app->singleton( DpiaService::class, function ( $app ): DpiaService {
            return new DpiaService(
                $app->make( RiskCalculator::class ),
                $app->make( ProcessingActivityService::class ),
            );
        } );

        // Monitoring + reporting
        $this->app->singleton( ComplianceMonitor::class, fn () => new ComplianceMonitor() );
        $this->app->singleton( ComplianceReportGenerator::class, fn () => new ComplianceReportGenerator() );
    }

    /**
     * Register compliance middleware aliases (gated on per-domain config).
     */
    protected function registerComplianceMiddleware(): void
    {
        $router = $this->app['router'];

        if ( config( 'artisanpack.compliance.compliance.consent.enabled', true ) ) {
            $router->aliasMiddleware( 'check.consent', CheckConsentMiddleware::class );
        }

        if ( config( 'artisanpack.compliance.compliance.minimization.enabled', true ) ) {
            $router->aliasMiddleware( 'data.minimization', DataMinimizationMiddleware::class );
        }
    }

    /**
     * Register compliance event listeners — log every consent /
     * erasure / data-export / compliance-check / violation event so
     * apps get an out-of-the-box audit trail without wiring listeners
     * themselves.
     */
    protected function registerComplianceEventListeners(): void
    {
        Event::listen( ConsentGranted::class, function ( $event ): void {
            Log::info( 'Consent granted', [
                'user_id'   => $event->consentRecord->user_id,
                'policy_id' => $event->consentRecord->consent_policy_id,
            ] );
        } );

        Event::listen( ConsentWithdrawn::class, function ( $event ): void {
            Log::info( 'Consent withdrawn', [
                'user_id'   => $event->consentRecord->user_id,
                'policy_id' => $event->consentRecord->consent_policy_id,
            ] );
        } );

        Event::listen( ErasureRequested::class, function ( $event ): void {
            Log::info( 'Erasure requested', [
                'request_number' => $event->request->request_number,
                'user_id'        => $event->request->user_id,
            ] );
        } );

        Event::listen( ErasureCompleted::class, function ( $event ): void {
            Log::info( 'Erasure completed', [
                'request_number' => $event->request->request_number,
                'user_id'        => $event->request->user_id,
            ] );
        } );

        Event::listen( DataExportRequested::class, function ( $event ): void {
            Log::info( 'Data export requested', [
                'request_number' => $event->request->request_number,
                'user_id'        => $event->request->user_id,
            ] );
        } );

        Event::listen( DataExportCompleted::class, function ( $event ): void {
            Log::info( 'Data export completed', [
                'request_number' => $event->request->request_number,
                'user_id'        => $event->request->user_id,
            ] );
        } );

        Event::listen( ComplianceCheckCompleted::class, function ( $event ): void {
            if ( ! $event->result->passed ) {
                Log::warning( 'Compliance check failed', [
                    'check'   => $event->result->checkName,
                    'message' => $event->result->message,
                ] );
            }
        } );

        Event::listen( ComplianceViolationDetected::class, function ( $event ): void {
            Log::error( 'Compliance violation detected', [
                'violation_number' => $event->violation->violation_number,
                'severity'         => $event->violation->severity,
                'category'         => $event->violation->category,
                'title'            => $event->violation->title,
            ] );
        } );
    }

    /**
     * Default-deny gate for compliance dashboard access. Apps should
     * override in their AuthServiceProvider:
     *
     *   Gate::define('viewComplianceDashboard',
     *       fn ($user) => $user->hasRole('compliance-officer'));
     */
    protected function registerComplianceDashboardGate(): void
    {
        if ( ! Gate::has( 'viewComplianceDashboard' ) ) {
            Gate::define( 'viewComplianceDashboard', fn ( $user ) => false);
        }
    }
}
