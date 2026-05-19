<?php

/**
 * Collection policy model — declarative rule for what fields may be collected for a given purpose.
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

class CollectionPolicy extends Model
{
    protected $table = 'collection_policies';

    protected $fillable = [
        'name',
        'purpose',
        'allowed_fields',
        'required_fields',
        'conditional_fields',
        'prohibited_fields',
        'legal_basis',
        'consent_type',
        'minimization_rules',
        'is_active',
    ];

    public function scopeActive( Builder $query ): Builder
    {
        return $query->where( 'is_active', true );
    }

    public static function getForPurpose( string $purpose ): ?self
    {
        return self::query()->active()->where( 'purpose', $purpose )->first();
    }

    protected function casts(): array
    {
        return [
            'allowed_fields'     => 'array',
            'required_fields'    => 'array',
            'conditional_fields' => 'array',
            'prohibited_fields'  => 'array',
            'minimization_rules' => 'array',
            'is_active'          => 'boolean',
        ];
    }
}
