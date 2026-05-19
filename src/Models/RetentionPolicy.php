<?php

/**
 * Retention policy model — declarative rule for how long a data category / model is kept.
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

class RetentionPolicy extends Model
{
    protected $table = 'retention_policies';

    protected $fillable = [
        'name',
        'description',
        'model_class',
        'data_category',
        'retention_days',
        'legal_basis',
        'deletion_strategy',
        'archive_location',
        'conditions',
        'exceptions',
        'notification_days',
        'is_active',
        'created_by',
    ];

    public function scopeActive( Builder $query ): Builder
    {
        return $query->where( 'is_active', true );
    }

    public static function getForPurpose( string $modelClass ): ?self
    {
        return self::query()->active()->where( 'model_class', $modelClass )->first();
    }

    protected function casts(): array
    {
        return [
            'retention_days'    => 'integer',
            'notification_days' => 'integer',
            'conditions'        => 'array',
            'exceptions'        => 'array',
            'is_active'         => 'boolean',
        ];
    }
}
