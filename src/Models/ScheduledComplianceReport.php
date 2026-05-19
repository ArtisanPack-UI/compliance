<?php

/**
 * Scheduled compliance report model — cron-driven recurring compliance report delivery.
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

class ScheduledComplianceReport extends Model
{
    protected $table = 'scheduled_compliance_reports';

    protected $fillable = [
        'report_type',
        'name',
        'cron_expression',
        'recipients',
        'options',
        'format',
        'is_active',
        'last_run_at',
        'next_run_at',
    ];

    public function scopeActive( Builder $query ): Builder
    {
        return $query->where( 'is_active', true );
    }

    protected function casts(): array
    {
        return [
            'recipients'  => 'array',
            'options'     => 'array',
            'is_active'   => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }
}
