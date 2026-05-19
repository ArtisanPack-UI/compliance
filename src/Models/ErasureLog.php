<?php

/**
 * Erasure log model — per-handler audit row recording what happened during an erasure run.
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

class ErasureLog extends Model
{
    protected $table = 'erasure_logs';

    protected $fillable = [
        'request_id',
        'handler_name',
        'action',
        'status',
        'records_found',
        'records_erased',
        'records_retained',
        'retention_reason',
        'backup_reference',
        'error_message',
        'metadata',
        'started_at',
        'completed_at',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo( ErasureRequest::class, 'request_id' );
    }

    protected function casts(): array
    {
        return [
            'records_found'    => 'integer',
            'records_erased'   => 'integer',
            'records_retained' => 'integer',
            'metadata'         => 'array',
            'started_at'       => 'datetime',
            'completed_at'     => 'datetime',
        ];
    }
}
