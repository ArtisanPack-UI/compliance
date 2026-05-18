<?php

/**
 * Consent policy model — versioned legal text + processing details for a consent purpose.
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

class ConsentPolicy extends Model
{
    protected $table = 'consent_policies';

    protected $fillable = [
        'purpose',
        'name',
        'description',
        'legal_text',
        'version',
        'previous_version_id',
        'data_categories',
        'processing_details',
        'retention_period',
        'third_party_sharing',
        'rights_description',
        'withdrawal_consequences',
        'is_required',
        'is_active',
        'requires_explicit',
        'minimum_age',
        'effective_at',
        'expires_at',
        'changes_from_previous',
        'created_by',
    ];

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo( self::class, 'previous_version_id' );
    }

    public function consentRecords(): HasMany
    {
        return $this->hasMany( ConsentRecord::class, 'policy_id' );
    }

    public function scopeActive( Builder $query ): Builder
    {
        return $query->where( 'is_active', true );
    }

    public function scopeEffective( Builder $query ): Builder
    {
        return $query->where( 'effective_at', '<=', now() )
            ->where( function ( Builder $q ): void {
                $q->whereNull( 'expires_at' )->orWhere( 'expires_at', '>', now() );
            } );
    }

    public static function getLatestForPurpose( string $purpose ): ?self
    {
        return self::query()
            ->where( 'purpose', $purpose )
            ->active()
            ->effective()
            ->latest( 'effective_at' )
            ->first();
    }

    protected function casts(): array
    {
        return [
            'data_categories'       => 'array',
            'processing_details'    => 'array',
            'third_party_sharing'   => 'array',
            'changes_from_previous' => 'array',
            'is_required'           => 'boolean',
            'is_active'             => 'boolean',
            'requires_explicit'     => 'boolean',
            'minimum_age'           => 'integer',
            'effective_at'          => 'datetime',
            'expires_at'            => 'datetime',
        ];
    }
}
