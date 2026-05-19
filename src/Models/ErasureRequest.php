<?php

/**
 * Erasure request model — GDPR Article 17 "right to be forgotten" request lifecycle.
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
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErasureRequest extends Model
{
    protected $table = 'erasure_requests';

    protected $fillable = [
        'request_number',
        'user_id',
        'requester_type',
        'requester_contact',
        'status',
        'scope',
        'specific_data',
        'reason',
        'identity_verified',
        'identity_verified_at',
        'identity_verified_method',
        'exemptions_found',
        'exemption_explanation',
        'handlers_processed',
        'handlers_failed',
        'third_parties_notified',
        'certificate_path',
        'completed_at',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'deadline_at',
        'created_by',
        'processed_by',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany( ErasureLog::class, 'request_id' );
    }

    public function scopePending( Builder $query ): Builder
    {
        return $query->whereIn( 'status', ['pending', 'verifying', 'approved', 'processing'] );
    }

    public function scopeOverdue( Builder $query ): Builder
    {
        return $query->pending()->where( 'deadline_at', '<', now() );
    }

    protected function casts(): array
    {
        return [
            'specific_data'          => 'array',
            'exemptions_found'       => 'array',
            'handlers_processed'     => 'array',
            'handlers_failed'        => 'array',
            'third_parties_notified' => 'array',
            'identity_verified'      => 'boolean',
            'identity_verified_at'   => 'datetime',
            'completed_at'           => 'datetime',
            'rejected_at'            => 'datetime',
            'deadline_at'            => 'datetime',
        ];
    }
}
