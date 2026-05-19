<?php

/**
 * Consent record model — per-user, per-purpose consent state with versioned policy reference.
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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsentRecord extends Model
{
    protected $table = 'consent_records';

    protected $fillable = [
        'user_id',
        'purpose',
        'policy_id',
        'policy_version',
        'status',
        'consent_type',
        'collection_method',
        'collection_context',
        'ip_address',
        'user_agent',
        'proof_reference',
        'granular_choices',
        'granted_at',
        'expires_at',
        'withdrawn_at',
        'withdrawal_reason',
        'metadata',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo( ConsentPolicy::class, 'policy_id' );
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany( ConsentAuditLog::class, 'consent_record_id' );
    }

    public function isExpired(): bool
    {
        return null !== $this->expires_at && $this->expires_at->isPast();
    }

    public function scopeValid( Builder $query ): Builder
    {
        return $query->where( 'status', 'granted' )
            ->where( function ( Builder $q ): void {
                $q->whereNull( 'expires_at' )->orWhere( 'expires_at', '>', now() );
            } );
    }

    protected function casts(): array
    {
        return [
            'collection_context' => 'array',
            'granular_choices'   => 'array',
            'metadata'           => 'array',
            'granted_at'         => 'datetime',
            'expires_at'         => 'datetime',
            'withdrawn_at'       => 'datetime',
        ];
    }
}
