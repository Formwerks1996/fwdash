<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            PhasesSeeder::class,
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'nathan@formwerks.ca'],
            ['name' => 'Nathan'],
        );

        $admin->syncRoles(Role::Admin->value);
    }
}
