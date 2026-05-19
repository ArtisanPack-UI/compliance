<?php

/**
 * Consent audit log model — immutable change record for every consent state transition.
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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentAuditLog extends Model
{
    protected $table = 'consent_audit_logs';

    protected $fillable = [
        'consent_record_id',
        'user_id',
        'action',
        'purpose',
        'old_status',
        'new_status',
        'policy_version',
        'actor_type',
        'actor_id',
        'reason',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    public function consentRecord(): BelongsTo
    {
        return $this->belongsTo( ConsentRecord::class, 'consent_record_id' );
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
