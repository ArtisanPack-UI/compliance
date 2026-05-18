<?php

/**
 * Compliance score model — aggregate compliance posture snapshot for a regulation or "all".
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

class ComplianceScore extends Model
{
    protected $table = 'compliance_scores';

    protected $fillable = [
        'overall_score',
        'regulation',
        'category_scores',
        'findings',
        'recommendations',
        'calculated_at',
        'next_calculation_at',
        'calculated_by',
    ];

    public static function getLatest( string $regulation = 'all' ): ?self
    {
        return self::query()
            ->where( 'regulation', $regulation )
            ->latest( 'calculated_at' )
            ->first();
    }

    public function getGrade(): string
    {
        $score = (float) $this->overall_score;

        return match ( true ) {
            $score >= 90.0 => 'A',
            $score >= 80.0 => 'B',
            $score >= 70.0 => 'C',
            $score >= 60.0 => 'D',
            default        => 'F',
        };
    }

    protected function casts(): array
    {
        return [
            'overall_score'       => 'decimal:2',
            'category_scores'     => 'array',
            'findings'            => 'array',
            'recommendations'     => 'array',
            'calculated_at'       => 'datetime',
            'next_calculation_at' => 'datetime',
        ];
    }
}
