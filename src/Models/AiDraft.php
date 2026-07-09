<?php

/**
 * AI-generated compliance draft — append-only versioned store for
 * privacy-policy, DPIA, and consent-text outputs.
 *
 * Drafts are NEVER updated. Every agent run creates a new row so the
 * revision history is preserved and legal reviewers can compare
 * versions. Enforced at the model level via {@see UPDATED_AT} being
 * `null` and the {@see saving()} guard in the boot method.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class AiDraft extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'compliance_ai_drafts';

    protected $fillable = [
        'feature_key',
        'subject_key',
        'content',
        'metadata',
        'created_by',
    ];

    public function scopeForFeature( Builder $query, string $featureKey ): Builder
    {
        return $query->where( 'feature_key', $featureKey );
    }

    public function scopeForSubject( Builder $query, string $subjectKey ): Builder
    {
        return $query->where( 'subject_key', $subjectKey );
    }

    protected static function booted(): void
    {
        static::updating( function ( AiDraft $draft ): void {
            throw new RuntimeException(
                'Compliance AI drafts are append-only. Create a new draft instead of updating draft #' . $draft->id . '.',
            );
        } );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content'    => 'array',
            'metadata'   => 'array',
            'created_at' => 'datetime',
        ];
    }
}
