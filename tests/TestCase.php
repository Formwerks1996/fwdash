<?php

namespace Tests;

use Database\Seeders\PhasesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Boots from this checkout even when vendor/ is a symlink to another disk. */
    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUpRolesAndPhases(): void
    {
        $this->seed([RolesAndPermissionsSeeder::class, PhasesSeeder::class]);
    }
}
