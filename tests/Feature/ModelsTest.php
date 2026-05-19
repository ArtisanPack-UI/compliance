<?php

declare( strict_types=1 );

use ArtisanPackUI\Compliance\Models\AssessmentRisk;
use ArtisanPackUI\Compliance\Models\ComplianceCheckResult;
use ArtisanPackUI\Compliance\Models\ComplianceScore;
use ArtisanPackUI\Compliance\Models\ConsentPolicy;
use ArtisanPackUI\Compliance\Models\ConsentRecord;
use ArtisanPackUI\Compliance\Models\DataProtectionAssessment;
use ArtisanPackUI\Compliance\Models\ErasureRequest;
use ArtisanPackUI\Compliance\Models\PortabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

it( 'persists and casts ConsentPolicy JSON columns', function (): void {
    $policy = ConsentPolicy::create( [
        'purpose'           => 'analytics',
        'name'              => 'Analytics tracking',
        'legal_text'        => 'We track page views.',
        'version'           => '1.0',
        'data_categories'   => ['behavioral', 'technical'],
        'is_required'       => false,
        'is_active'         => true,
        'requires_explicit' => true,
        'minimum_age'       => 16,
        'effective_at'      => now(),
    ] );

    expect( $policy->data_categories )->toBe( ['behavioral', 'technical'] );
    expect( $policy->is_active )->toBeTrue();
    expect( $policy->effective_at )->not->toBeNull();
} );

it( 'ConsentPolicy::getLatestForPurpose returns the active effective policy', function (): void {
    ConsentPolicy::create( [
        'purpose'      => 'marketing',
        'name'         => 'Old marketing',
        'legal_text'   => 'old',
        'version'      => '1.0',
        'is_active'    => false,
        'effective_at' => now()->subYear(),
    ] );

    $latest = ConsentPolicy::create( [
        'purpose'      => 'marketing',
        'name'         => 'New marketing',
        'legal_text'   => 'new',
        'version'      => '2.0',
        'is_active'    => true,
        'effective_at' => now()->subDay(),
    ] );

    $found = ConsentPolicy::getLatestForPurpose( 'marketing' );

    expect( $found )->not->toBeNull();
    expect( $found->id )->toBe( $latest->id );
} );

it( 'ConsentRecord scopeValid filters granted + unexpired', function (): void {
    $policy = ConsentPolicy::create( [
        'purpose'      => 'cookies',
        'name'         => 'Cookies',
        'legal_text'   => 'foo',
        'version'      => '1.0',
        'effective_at' => now(),
    ] );

    ConsentRecord::create( [
        'user_id'           => 1,
        'purpose'           => 'cookies',
        'policy_id'         => $policy->id,
        'policy_version'    => '1.0',
        'status'            => 'granted',
        'consent_type'      => 'explicit',
        'collection_method' => 'banner',
        'granted_at'        => now(),
    ] );

    ConsentRecord::create( [
        'user_id'           => 2,
        'purpose'           => 'cookies',
        'policy_id'         => $policy->id,
        'policy_version'    => '1.0',
        'status'            => 'withdrawn',
        'consent_type'      => 'explicit',
        'collection_method' => 'banner',
        'withdrawn_at'      => now(),
    ] );

    expect( ConsentRecord::valid()->count() )->toBe( 1 );
} );

it( 'DataProtectionAssessment has many AssessmentRisks', function (): void {
    $assessment = DataProtectionAssessment::create( [
        'assessment_number' => 'DPIA-001',
        'title'             => 'New feature DPIA',
        'status'            => 'draft',
        'version'           => 1,
    ] );

    $assessment->risks()->create( [
        'risk_category'   => 'confidentiality',
        'risk_title'      => 'Unauthorized access',
        'likelihood'      => 'possible',
        'impact'          => 'major',
        'status'          => 'identified',
    ] );

    expect( $assessment->risks )->toHaveCount( 1 );
    expect( $assessment->risks->first() )->toBeInstanceOf( AssessmentRisk::class );
} );

it( 'AssessmentRisk computes inherent score and risk level', function (): void {
    $risk = new AssessmentRisk( [
        'likelihood' => 'likely',
        'impact'     => 'severe',
    ] );

    expect( $risk->calculateInherentScore() )->toBe( 20.0 );
    expect( AssessmentRisk::determineRiskLevel( 20.0 ) )->toBe( 'critical' );
    expect( AssessmentRisk::determineRiskLevel( 5.0 ) )->toBe( 'low' );
} );

it( 'ErasureRequest scopePending matches in-flight statuses', function (): void {
    $base = [
        'request_number' => '',
        'user_id'        => 1,
        'requester_type' => 'self',
        'scope'          => 'full',
        'deadline_at'    => now()->addMonth(),
    ];

    foreach ( ['pending', 'processing', 'completed', 'rejected'] as $status ) {
        ErasureRequest::create( array_merge( $base, [
            'request_number' => 'ER-' . $status,
            'status'         => $status,
        ] ) );
    }

    expect( ErasureRequest::pending()->count() )->toBe( 2 );
} );

it( 'PortabilityRequest canDownload respects status, expiry, and limit', function (): void {
    $req = PortabilityRequest::create( [
        'request_number' => 'PR-1',
        'user_id'        => 1,
        'requester_type' => 'self',
        'status'         => 'completed',
        'format'         => 'json',
        'transfer_type'  => 'download',
        'download_count' => 0,
        'download_limit' => 3,
        'expires_at'     => now()->addDay(),
        'deadline_at'    => now()->addMonth(),
    ] );

    expect( $req->canDownload() )->toBeTrue();

    $req->update( ['download_count' => 3] );
    $req->refresh();
    expect( $req->canDownload() )->toBeFalse();
} );

it( 'ComplianceCheckResult::isPassed / isFailed reflect status', function (): void {
    $passed = new ComplianceCheckResult( ['check_name' => 'x', 'status' => 'passed'] );
    $failed = new ComplianceCheckResult( ['check_name' => 'x', 'status' => 'failed'] );
    $error  = new ComplianceCheckResult( ['check_name' => 'x', 'status' => 'error'] );

    expect( $passed->isPassed() )->toBeTrue();
    expect( $failed->isFailed() )->toBeTrue();
    expect( $error->isFailed() )->toBeTrue();
} );

it( 'ComplianceScore::getGrade maps score to a letter grade', function (): void {
    $cases = [
        95.0 => 'A',
        82.0 => 'B',
        71.0 => 'C',
        61.0 => 'D',
        40.0 => 'F',
    ];

    foreach ( $cases as $score => $grade ) {
        $row = new ComplianceScore( ['overall_score' => $score, 'regulation' => 'all'] );

        expect( $row->getGrade() )->toBe( $grade );
    }
} );
