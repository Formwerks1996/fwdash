<?php

namespace App\Livewire;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Users')]
class Users extends Component
{
    /** @return Collection<int, User> */
    #[Computed]
    public function users(): Collection
    {
        return User::with('roles')->orderBy('name')->get();
    }

    public function setRole(int $userId, string $role, AuditLogger $audit): void
    {
        $this->authorize(Permission::ManageUsers->value);

        $role = Role::from($role);
        $user = User::findOrFail($userId);

        if ($user->is(auth()->user()) && $role !== Role::Admin) {
            $this->addError('role', __('You cannot remove your own admin access.'));

            return;
        }

        DB::transaction(function () use ($user, $role, $audit) {
            $before = ['role' => $user->primaryRole()->value];
            $user->syncRoles($role->value);

            $audit->log('update', 'users', $user->id, before: $before, after: ['role' => $role->value]);
        });

        unset($this->users);
    }

    public function render(): View
    {
        return view('livewire.users', ['roles' => Role::cases()]);
    }
}
