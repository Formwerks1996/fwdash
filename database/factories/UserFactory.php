<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'microsoft_id' => (string) Str::uuid(),
            'remember_token' => Str::random(10),
        ];
    }

    public function role(Role $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->syncRoles($role->value));
    }

    public function admin(): static
    {
        return $this->role(Role::Admin);
    }

    public function manager(): static
    {
        return $this->role(Role::Manager);
    }

    public function staff(): static
    {
        return $this->role(Role::Staff);
    }
}
