<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Someone who can appear on the Team Lineup and has a PM Desk. Keyed by their
 * staff name, never by staff.id: the workbook upload replaces staff.
 *
 * @property int $id
 * @property string $name
 * @property ?string $full_name
 * @property ?string $email
 * @property string $tier
 * @property ?string $grp
 * @property ?string $default_boss
 * @property bool $active
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['name', 'full_name', 'email', 'tier', 'grp', 'default_boss', 'active'])]
class TeamMember extends Model
{
    public const Tiers = ['partner', 'pm', 'staff'];

    public const Groups = ['tech', 'other'];

    /** The lineup is two columns: one per partner. */
    public const MaxPartners = 2;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /** @param  Builder<TeamMember>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function displayName(): string
    {
        return $this->full_name ?: $this->name;
    }

    public function firstName(): string
    {
        return Str::before($this->displayName(), ' ');
    }

    public function isPartner(): bool
    {
        return $this->tier === 'partner';
    }

    public function isPm(): bool
    {
        return $this->tier === 'pm';
    }

    /** "pm", "tech" or "other": the column of the Not placed strip; null for partners. */
    public function group(): ?string
    {
        return match ($this->tier) {
            'partner' => null,
            'pm' => 'pm',
            default => $this->grp ?? 'other',
        };
    }
}
