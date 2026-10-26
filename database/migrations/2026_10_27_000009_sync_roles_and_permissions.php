<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

/*
 * Roles and permissions are defined in code (App\Enums\Role); this applies
 * the current definition on deploy so a new permission reaches every role.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }
};
