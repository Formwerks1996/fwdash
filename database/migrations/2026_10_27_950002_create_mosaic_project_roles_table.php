<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Who Mosaic has on each project, and in what role: a copy of Mosaic's member
 * project roles, replaced by `mosaic:import-roles`. Separate from the PM Desk's
 * project_assignments, which the team edits here. People are keyed by email
 * (how a sign-in finds them) and projects by job number, with no foreign keys:
 * the workbook upload replaces projects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mosaic_project_roles', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('job_number')->index();
            $table->string('role')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mosaic_project_roles');
    }
};
