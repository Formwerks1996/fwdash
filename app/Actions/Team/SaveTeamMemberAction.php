<?php

namespace App\Actions\Team;

use App\Models\TeamMember;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * The lineup roster. A name is the key every week and assignment uses, so it
 * is set once and never renamed; take someone off with Active instead.
 * Deleting a person leaves past weeks alone: unknown names are skipped on read.
 */
class SaveTeamMemberAction
{
    public function __construct(protected AuditLogger $audit) {}

    /** @param array{name?: string, full_name?: ?string, email?: ?string, tier?: string, grp?: ?string, default_boss?: ?string, active?: bool} $attributes */
    public function execute(array $attributes, ?TeamMember $member = null): TeamMember
    {
        $values = $this->validated($attributes, $member);

        return DB::transaction(function () use ($values, $member) {
            if (! $member) {
                $member = TeamMember::create($values);

                $this->audit->log('team.add', 'team_members', $member->name, after: $values);

                return $member;
            }

            unset($values['name']);
            $member->fill($values);
            $changed = $member->getDirty();

            if (! $changed) {
                return $member;
            }

            $before = collect($member->getOriginal())->only(array_keys($changed))->all();
            $member->save();

            $this->audit->log('team.set', 'team_members', $member->name, before: $before, after: $changed);

            return $member;
        });
    }

    public function delete(TeamMember $member): void
    {
        DB::transaction(function () use ($member) {
            $before = $member->only(['name', 'full_name', 'email', 'tier', 'grp', 'default_boss', 'active']);
            $member->delete();

            $this->audit->log('team.delete', 'team_members', $member->name, before: $before);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{name: string, full_name: ?string, email: ?string, tier: string, grp: ?string, default_boss: ?string, active: bool}
     */
    protected function validated(array $attributes, ?TeamMember $member): array
    {
        $blank = fn (mixed $value) => is_string($value) && trim($value) !== '' ? trim($value) : null;

        $name = $member->name ?? $blank($attributes['name'] ?? null);
        $tier = $attributes['tier'] ?? $member->tier ?? 'staff';
        $active = (bool) ($attributes['active'] ?? $member->active ?? true);
        $email = $blank($attributes['email'] ?? null);
        $email = $email === null ? null : mb_strtolower($email);

        if ($name === null || mb_strlen($name) > 60) {
            throw ValidationException::withMessages(['form.name' => __('A person needs a name (as in the Reference sheet).')]);
        }

        if (! $member && TeamMember::where('name', $name)->exists()) {
            throw ValidationException::withMessages(['form.name' => __(':name is already on the roster.', ['name' => $name])]);
        }

        if (! in_array($tier, TeamMember::Tiers, true)) {
            throw ValidationException::withMessages(['form.tier' => __('A tier is partner, pm or staff.')]);
        }

        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['form.email' => __('That is not an email address.')]);
        }

        if ($email !== null && TeamMember::where('email', $email)->when($member, fn ($query) => $query->whereKeyNot($member->id))->exists()) {
            throw ValidationException::withMessages(['form.email' => __('Someone else on the roster has that email.')]);
        }

        $grp = $tier === 'staff' ? ($blank($attributes['grp'] ?? null) ?? 'other') : null;

        if ($grp !== null && ! in_array($grp, TeamMember::Groups, true)) {
            throw ValidationException::withMessages(['form.grp' => __('A group is tech or other.')]);
        }

        $defaultBoss = $tier === 'pm' ? $blank($attributes['default_boss'] ?? null) : null;

        if ($defaultBoss !== null && ! TeamMember::active()->where('tier', 'partner')->where('name', $defaultBoss)->exists()) {
            throw ValidationException::withMessages(['form.default_boss' => __('A default boss must be one of the partners.')]);
        }

        if ($tier === 'partner' && $active) {
            $partners = TeamMember::active()
                ->where('tier', 'partner')
                ->when($member, fn ($query) => $query->whereKeyNot($member->id))
                ->count();

            if ($partners >= TeamMember::MaxPartners) {
                throw ValidationException::withMessages(['form.tier' => __('The lineup has room for two partners.')]);
            }
        }

        return [
            'name' => $name,
            'full_name' => $blank($attributes['full_name'] ?? null),
            'email' => $email,
            'tier' => $tier,
            'grp' => $grp,
            'default_boss' => $defaultBoss,
            'active' => $active,
        ];
    }
}
