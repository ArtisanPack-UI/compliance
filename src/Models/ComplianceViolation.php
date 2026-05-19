<?php

/**
 * Compliance violation model — raised by a check, tracked through to resolution.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComplianceViolation extends Model
{
    protected $table = 'compliance_violations';

    protected $fillable = [
        'violation_number',
        'check_name',
        'category',
        'regulation',
        'article_reference',
        'severity',
        'title',
        'description',
        'affected_records',
        'affected_count',
        'evidence',
        'remediation_steps',
        'remediation_deadline',
        'status',
        'assigned_to',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'accepted_risk',
        'risk_acceptance_by',
        'risk_acceptance_reason',
    ];

    public function scopeOpen( Builder $query ): Builder
    {
        return $query->whereIn( 'status', ['open', 'acknowledged', 'in_progress'] );
    }

    protected function casts(): array
    {
        return [
            'affected_records'     => 'array',
            'evidence'             => 'array',
            'remediation_steps'    => 'array',
            'affected_count'       => 'integer',
            'accepted_risk'        => 'boolean',
            'remediation_deadline' => 'datetime',
            'acknowledged_at'      => 'datetime',
            'resolved_at'          => 'datetime',
        ];
    }
}
