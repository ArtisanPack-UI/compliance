<?php

/**
 * RetentionPolicyCheck component of the Compliance package.
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
use ArtisanPackUI\Compliance\Models\RetentionPolicy;
use Exception;
use Illuminate\Support\Facades\DB;

class RetentionPolicyCheck extends BaseComplianceCheck
{
    protected string $name = 'retention_policy';

    protected string $description = 'Checks that data retention policies are defined and enforced';

    protected string $category = 'data_minimization';

    protected array $regulations = ['gdpr', 'ccpa'];

    protected string $severity = 'medium';

    protected string $remediation = 'Define retention policies for all data categories. Implement automated purging of expired data.';

    /**
     * Run the check.
     */
    public function run(): CheckResult
    {
        $violations = [];
        $warnings   = [];

        // Check for active retention policies
        $policies = RetentionPolicy::active()->get();

        if ( $policies->isEmpty() ) {
            $violations[] = 'No retention policies defined';

            return $this->failed( $violations, 1, 0 );
        }

        $checked   = $policies->count();
        $compliant = 0;

        foreach ( $policies as $policy ) {
            // Check if policy has model class
            if ( empty( $policy->model_class ) ) {
                $warnings[] = "Policy '{$policy->name}' has no associated model class";

                continue;
            }

            // Check if model class exists
            if ( ! class_exists( $policy->model_class ) ) {
                $violations[] = "Policy '{$policy->name}' references non-existent model class";

                continue;
            }

            // Verify the configured class is actually an Eloquent model
            // before instantiating it. The value comes from the database,
            // so a misconfigured policy could otherwise instantiate
            // arbitrary classes here.
            if ( ! is_subclass_of( $policy->model_class, \Illuminate\Database\Eloquent\Model::class ) ) {
                $violations[] = "Policy '{$policy->name}' model class is not an Eloquent model";

                continue;
            }

            // Check for data exceeding retention
            if ( null !== $policy->retention_days ) {
                try {
                    $expiredCount = DB::table( (new $policy->model_class)->getTable() )
                        ->where( 'created_at', '<', now()->subDays( $policy->retention_days ) )
                        ->count();

                    if ( $expiredCount > 0 ) {
                        $warnings[] = "Policy '{$policy->name}': {$expiredCount} records exceed retention period";
                    }
                } catch ( Exception $e ) {
                    $warnings[] = "Could not check policy '{$policy->name}': " . $e->getMessage();
                }
            }

            $compliant++;
        }

        $details = [
            'total_policies'   => $checked,
            'policies_checked' => array_map( fn ( $p ) => $p->name, $policies->all() ),
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
