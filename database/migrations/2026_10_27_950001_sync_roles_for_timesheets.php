<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

/*
 * Gives every role the new timesheet permissions on deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }
};
