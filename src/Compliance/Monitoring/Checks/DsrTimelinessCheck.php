<?php

/**
 * DsrTimelinessCheck component of the Compliance package.
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
use ArtisanPackUI\Compliance\Models\ErasureRequest;
use ArtisanPackUI\Compliance\Models\PortabilityRequest;

class DsrTimelinessCheck extends BaseComplianceCheck
{
    protected string $name = 'dsr_timeliness';

    protected string $description = 'Checks that Data Subject Requests are processed within legal deadlines';

    protected string $category = 'data_subject_rights';

    protected array $regulations = ['gdpr', 'ccpa', 'lgpd'];

    protected string $severity = 'critical';

    protected string $remediation = 'Process overdue requests immediately. Review and optimize request handling procedures to prevent future delays.';

    /**
     * Run the check.
     */
    public function run(): CheckResult
    {
        $violations = [];

        // Check erasure requests
        $overdueErasure = ErasureRequest::overdue()->get();
        foreach ( $overdueErasure as $request ) {
            $daysOverdue  = $request->deadline_at->diffInDays( now() );
            $violations[] = "Erasure request {$request->request_number} is {$daysOverdue} days overdue";
        }

        // Check portability requests
        $overduePortability = PortabilityRequest::pending()
            ->where( 'deadline_at', '<', now() )
            ->get();
        foreach ( $overduePortability as $request ) {
            $daysOverdue  = $request->deadline_at->diffInDays( now() );
            $violations[] = "Portability request {$request->request_number} is {$daysOverdue} days overdue";
        }

        // Calculate statistics
        $totalErasure     = ErasureRequest::pending()->count();
        $totalPortability = PortabilityRequest::pending()->count();
        $totalPending     = $totalErasure + $totalPortability;
        $totalOverdue     = count( $violations );

        $checked   = $totalPending;
        $compliant = $totalPending - $totalOverdue;

        $details = [
            'pending_erasure_requests'     => $totalErasure,
            'pending_portability_requests' => $totalPortability,
            'overdue_erasure'              => $overdueErasure->count(),
            'overdue_portability'          => $overduePortability->count(),
        ];

        if ( ! empty( $violations ) ) {
            return $this->failed( $violations, $checked, $compliant, $details );
        }

        return $this->passed( $checked, $compliant, $details );
    }
}
