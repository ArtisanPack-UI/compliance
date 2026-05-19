<?php

/**
 * Export schema model — versioned schema definition for portable-data export formats.
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

class ExportSchema extends Model
{
    protected $table = 'export_schemas';

    protected $fillable = [
        'name',
        'category',
        'version',
        'format',
        'schema_definition',
        'field_mappings',
        'transformations',
        'is_default',
        'is_active',
    ];

    public function scopeActive( Builder $query ): Builder
    {
        return $query->where( 'is_active', true );
    }

    protected function casts(): array
    {
        return [
            'schema_definition' => 'array',
            'field_mappings'    => 'array',
            'transformations'   => 'array',
            'is_default'        => 'boolean',
            'is_active'         => 'boolean',
        ];
    }
}
