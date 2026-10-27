<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use App\Livewire\Team\TeamLineup;
use App\Support\LineupTree;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'microsoft_id', 'avatar', 'last_login_at'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles {
        checkPermissionTo as checkStoredPermissionTo;
    }
    use Notifiable;

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }

    public function primaryRole(): Role
    {
        return collect(Role::cases())->first(fn (Role $role) => $this->hasRole($role->value))
            ?? Role::Staff;
    }

    /**
     * The role an admin has chosen to preview the dashboard as from the
     * header toggle, or null when they are seeing it as themselves.
     */
    public function previewRole(): ?Role
    {
        if (! request()->hasSession() || ! $this->hasRole(Role::Admin->value)) {
            return null;
        }

        $role = Role::tryFrom((string) request()->session()->get('view_as'));

        return $role === Role::Admin ? null : $role;
    }

    /**
     * Spatie's gate asks this before any other check, so a preview has to
     * answer here or the admin's stored permissions would win.
     */
    public function checkPermissionTo($permission, ?string $guardName = null): bool
    {
        $previewRole = $this->previewRole();

        if ($previewRole === null) {
            return $this->checkStoredPermissionTo($permission, $guardName);
        }

        $ability = $permission instanceof \Spatie\Permission\Contracts\Permission ? $permission->name : (string) $permission;

        return in_array($ability, array_map(fn (Permission $allowed) => $allowed->value, $previewRole->permissions()), true);
    }

    /** The roster entry with this user's email, which is how a sign-in becomes a person on the team. */
    public function teamMember(): ?TeamMember
    {
        return TeamMember::where('email', Str::lower($this->email))->first();
    }

    /**
     * Job numbers this user is assigned to on the PM Desk.
     *
     * @return array<int, string>
     */
    public function assignedJobNumbers(): array
    {
        $member = $this->teamMember();

        return $member === null
            ? []
            : ProjectAssignment::where('member_name', $member->name)->pluck('job_number')->unique()->values()->all();
    }

    /**
     * Job numbers Mosaic has this user on, in any role that has not ended.
     *
     * @return array<int, string>
     */
    public function mosaicJobNumbers(): array
    {
        return MosaicProjectRole::query()
            ->where('email', Str::lower($this->email))
            ->current()
            ->pluck('job_number')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Job numbers this user may see: null for anyone with financial access,
     * otherwise their PM Desk assignments plus this week's Team Lineup project.
     *
     * @return ?array<int, string>
     */
    public function visibleJobNumbers(): ?array
    {
        if ($this->can(Permission::ViewFinancials->value)) {
            return null;
        }

        $member = $this->teamMember();

        if ($member === null) {
            return [];
        }

        $roster = LineupTree::roster();
        $lineup = LineupWeek::find(TeamLineup::thisMonday());
        $tagged = LineupTree::forWeek($lineup, $roster)->keepTags($lineup?->tags)[$member->name] ?? null;

        return collect([...$this->assignedJobNumbers(), $tagged])->filter()->unique()->values()->all();
    }

    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
