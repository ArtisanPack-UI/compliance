<?php

declare(strict_types=1);

use ArtisanPackUI\Compliance\Http\Controllers\Compliance\ConsentController;
use ArtisanPackUI\Compliance\Http\Controllers\Compliance\ErasureController;
use ArtisanPackUI\Compliance\Http\Controllers\Compliance\PortabilityController;
use ArtisanPackUI\Compliance\Http\Controllers\Compliance\ComplianceDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Compliance Routes
|--------------------------------------------------------------------------
|
| These routes handle GDPR/CCPA compliance features including consent
| management, data erasure (right to be forgotten), and data portability.
|
*/

Route::middleware(config('artisanpack.compliance.routes.middleware', ['web', 'auth']))
    ->prefix(config('artisanpack.compliance.routes.prefix', 'compliance'))
    ->name('compliance.')
    ->group(function () {

        // Consent Management
        Route::prefix('consent')->name('consent.')->group(function () {
            Route::get('/', [ConsentController::class, 'index'])->name('index');
            Route::get('/policies', [ConsentController::class, 'policies'])->name('policies');
            Route::get('/policies/{policy}', [ConsentController::class, 'showPolicy'])->name('policies.show');
            Route::post('/grant', [ConsentController::class, 'grant'])->name('grant');
            Route::post('/withdraw', [ConsentController::class, 'withdraw'])->name('withdraw');
            Route::get('/history', [ConsentController::class, 'history'])->name('history');
            Route::get('/verify/{policy}', [ConsentController::class, 'verify'])->name('verify');
        });

        // Data Erasure (Right to be Forgotten)
        Route::prefix('erasure')->name('erasure.')->group(function () {
            Route::get('/', [ErasureController::class, 'index'])->name('index');
            Route::post('/request', [ErasureController::class, 'request'])->name('request');
            Route::get('/status/{request}', [ErasureController::class, 'status'])->name('status');
            Route::post('/cancel/{request}', [ErasureController::class, 'cancel'])->name('cancel');
        });

        // Data Portability
        Route::prefix('portability')->name('portability.')->group(function () {
            Route::get('/', [PortabilityController::class, 'index'])->name('index');
            Route::post('/request', [PortabilityController::class, 'request'])->name('request');
            Route::get('/status/{request}', [PortabilityController::class, 'status'])->name('status');
            Route::get('/download/{request}', [PortabilityController::class, 'download'])->name('download');
        });

        // Compliance Dashboard (Admin only)
        Route::middleware(config('artisanpack.compliance.routes.admin_middleware', ['can:viewComplianceDashboard']))
            ->prefix('dashboard')
            ->name('dashboard.')
            ->group(function () {
                Route::get('/', [ComplianceDashboardController::class, 'index'])->name('index');
                Route::get('/violations', [ComplianceDashboardController::class, 'violations'])->name('violations');
                Route::get('/violations/{violation}', [ComplianceDashboardController::class, 'showViolation'])->name('violations.show');
                Route::post('/violations/{violation}/resolve', [ComplianceDashboardController::class, 'resolveViolation'])->name('violations.resolve');
                Route::get('/dsr-requests', [ComplianceDashboardController::class, 'dsrRequests'])->name('dsr-requests');
                Route::get('/consent-overview', [ComplianceDashboardController::class, 'consentOverview'])->name('consent-overview');
                Route::get('/dpia', [ComplianceDashboardController::class, 'dpiaOverview'])->name('dpia');
                Route::get('/reports', [ComplianceDashboardController::class, 'reports'])->name('reports');
                Route::post('/reports/generate', [ComplianceDashboardController::class, 'generateReport'])->name('reports.generate');
                Route::get('/reports/{report}/download', [ComplianceDashboardController::class, 'downloadReport'])->name('reports.download');
            });
    });

// Public cookie consent routes (no auth required)
Route::middleware(config('artisanpack.compliance.routes.public_middleware', ['web']))
    ->prefix(config('artisanpack.compliance.routes.prefix', 'compliance'))
    ->name('compliance.')
    ->group(function () {
        Route::get('/cookies/preferences', [ConsentController::class, 'cookiePreferences'])->name('cookies.preferences');
        Route::post('/cookies/save', [ConsentController::class, 'saveCookiePreferences'])->name('cookies.save');
    });
