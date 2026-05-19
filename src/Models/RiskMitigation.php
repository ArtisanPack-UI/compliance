<?php

/**
 * Risk mitigation model — a single mitigation measure against an assessment risk.
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

class RiskMitigation extends Model
{
    protected $table = 'risk_mitigations';

    protected $fillable = [
        'risk_id',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'assigned_to',
        'due_date',
        'implemented_at',
        'verified_at',
        'verified_by',
        'effectiveness_rating',
        'notes',
    ];

    public function risk(): BelongsTo
    {
        return $this->belongsTo( AssessmentRisk::class, 'risk_id' );
    }

    public function isOverdue(): bool
    {
        return null !== $this->due_date
            && $this->due_date->lt( today() )
            && ! in_array( $this->status, ['implemented', 'verified'], true );
    }

    protected function casts(): array
    {
        return [
            'due_date'             => 'date',
            'implemented_at'       => 'datetime',
            'verified_at'          => 'datetime',
            'effectiveness_rating' => 'integer',
        ];
    }
}
