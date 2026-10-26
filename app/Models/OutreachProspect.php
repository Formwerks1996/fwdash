<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property int $score
 * @property ?string $tier
 * @property ?string $region
 * @property ?string $img
 * @property bool $top
 * @property bool $has_contact
 * @property bool $has_linkedin
 * @property bool $has_email
 * @property ?array<string, mixed> $person
 * @property array<string, mixed> $activity
 * @property array<int, string> $types
 * @property array<int, string> $tags
 * @property array<int, string> $sources
 * @property array<int, string> $recent
 */
class OutreachProspect extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'top' => 'boolean',
            'has_contact' => 'boolean',
            'has_linkedin' => 'boolean',
            'has_email' => 'boolean',
            'person' => 'array',
            'activity' => 'array',
            'types' => 'array',
            'tags' => 'array',
            'sources' => 'array',
            'recent' => 'array',
        ];
    }

    /** @return HasMany<OutreachProject, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(OutreachProject::class, 'prospect_key', 'key')->orderBy('position');
    }
}
