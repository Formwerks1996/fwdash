<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $prospect_key
 * @property ?string $name
 * @property ?string $where_text
 * @property ?string $units
 * @property ?string $url
 * @property ?string $away
 * @property ?string $img
 * @property bool $hero
 * @property array<int, string> $types
 * @property ?string $lat
 * @property ?string $lon
 * @property bool $exact
 */
class OutreachProject extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'hero' => 'boolean',
            'exact' => 'boolean',
            'types' => 'array',
        ];
    }
}
