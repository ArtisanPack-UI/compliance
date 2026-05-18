<?php

/**
 * Portability request model — GDPR Article 20 data-portability request lifecycle.
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

class PortabilityRequest extends Model
{
    protected $table = 'portability_requests';

    protected $fillable = [
        'request_number',
        'user_id',
        'requester_type',
        'status',
        'format',
        'categories',
        'transfer_type',
        'destination_url',
        'destination_verified',
        'file_path',
        'file_size',
        'file_hash',
        'download_count',
        'download_limit',
        'expires_at',
        'completed_at',
        'downloaded_at',
        'deadline_at',
        'created_by',
    ];

    public function scopePending( Builder $query ): Builder
    {
        return $query->whereIn( 'status', ['pending', 'processing'] );
    }

    public function scopeExpired( Builder $query ): Builder
    {
        return $query->where( 'expires_at', '<', now() );
    }

    public function canDownload(): bool
    {
        if ( 'completed' !== $this->status ) {
            return false;
        }

        if ( null !== $this->expires_at && $this->expires_at->isPast() ) {
            return false;
        }

        return $this->download_count < $this->download_limit;
    }

    public function incrementDownloadCount(): void
    {
        $this->increment( 'download_count' );
        $this->forceFill( ['downloaded_at' => now()] )->save();
    }

    protected function casts(): array
    {
        return [
            'categories'           => 'array',
            'destination_verified' => 'boolean',
            'file_size'            => 'integer',
            'download_count'       => 'integer',
            'download_limit'       => 'integer',
            'expires_at'           => 'datetime',
            'completed_at'         => 'datetime',
            'downloaded_at'        => 'datetime',
            'deadline_at'          => 'datetime',
        ];
    }
}
