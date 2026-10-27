<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

/*
 * Opens the PM Desk to staff, and the Projects pages (scoped to their own
 * projects), on deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }
};
