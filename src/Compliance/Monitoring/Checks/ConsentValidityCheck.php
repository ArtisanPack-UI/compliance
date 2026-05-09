<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Monitoring\Checks;

use ArtisanPackUI\Compliance\Compliance\Monitoring\CheckResult;
use ArtisanPackUI\Compliance\Models\ConsentPolicy;
use ArtisanPackUI\Compliance\Models\ConsentRecord;

class ConsentValidityCheck extends BaseComplianceCheck
{
    protected string $name = 'consent_validity';

    protected string $description = 'Validates that all consent records are properly linked to active policies';

    protected string $category = 'consent';

    protected array $regulations = ['gdpr', 'ccpa', 'lgpd'];

    protected string $severity = 'high';

    protected string $remediation = 'Review consent records and ensure all are linked to valid, active consent policies. Request reconsent where necessary.';

    /**
     * Run the check.
     */
    public function run(): CheckResult
    {
        $violations = [];
        $warnings   = [];

        // Eager-load the related policy so we don't hit the DB once per
        // consent record inside the loop (N+1).
        $consents  = ConsentRecord::where( 'status', 'granted' )->with( 'policy' )->get();
        $checked   = $consents->count();
        $compliant = 0;

        foreach ( $consents as $consent ) {
            $policy = $consent->policy;

            if ( ! $policy ) {
                $violations[] = "Consent record {$consent->id} references non-existent policy";

                continue;
            }

            $hasWarning = false;

            if ( ! $policy->is_active ) {
                $warnings[] = "Consent record {$consent->id} references inactive policy";
                $hasWarning = true;
            }

            // Check if policy version matches
            $latestPolicy = ConsentPolicy::getLatestForPurpose( $consent->purpose );
            if ( $latestPolicy && $consent->policy_version !== $latestPolicy->version ) {
                $warnings[] = "Consent record {$consent->id} uses outdated policy version {$consent->policy_version}";
                $hasWarning = true;
            }

            // Only count records with no violations or warnings as compliant —
            // outdated-version / inactive-policy records still need follow-up.
            if ( ! $hasWarning ) {
                $compliant++;
            }
        }

        if ( ! empty( $violations ) ) {
            return $this->failed( $violations, $checked, $compliant, [
                'warnings' => $warnings,
            ] );
        }

        if ( ! empty( $warnings ) ) {
            return $this->warning( $warnings, $checked, $compliant );
        }

        return $this->passed( $checked, $compliant );
    }
}
