<?php

/**
 * Compliance check result model — single execution record of a compliance check.
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

class ComplianceCheckResult extends Model
{
    protected $table = 'compliance_check_results';

    protected $fillable = [
        'check_name',
        'status',
        'score',
        'violations_found',
        'warnings_found',
        'items_checked',
        'items_compliant',
        'details',
        'execution_time_ms',
        'next_run_at',
        'metadata',
    ];

    public function scopeForCheck( Builder $query, string $checkName ): Builder
    {
        return $query->where( 'check_name', $checkName );
    }

    public static function getLatestForCheck( string $checkName ): ?self
    {
        return self::query()->forCheck( $checkName )->latest()->first();
    }

    public function isPassed(): bool
    {
        return 'passed' === $this->status;
    }

    public function isFailed(): bool
    {
        return 'failed' === $this->status || 'error' === $this->status;
    }

    protected function casts(): array
    {
        return [
            'score'             => 'decimal:2',
            'violations_found'  => 'integer',
            'warnings_found'    => 'integer',
            'items_checked'     => 'integer',
            'items_compliant'   => 'integer',
            'execution_time_ms' => 'integer',
            'details'           => 'array',
            'metadata'          => 'array',
            'next_run_at'       => 'datetime',
        ];
    }
}
