<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $week the Monday, Y-m-d
 * @property array<string, string> $placements person => the person they go to, or "none"
 * @property array<string, string> $tags person => job number
 * @property array<string, array{type: string, note?: string}> $badges
 * @property ?int $updated_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['week', 'placements', 'tags', 'badges', 'updated_by'])]
class LineupWeek extends Model
{
    protected $primaryKey = 'week';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'placements' => 'array',
            'tags' => 'array',
            'badges' => 'array',
        ];
    }

    public function getWeekAttribute(mixed $value): string
    {
        return substr((string) $value, 0, 10);
    }
}
