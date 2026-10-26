<?php

use Database\Seeders\TeamSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The team: who is on the lineup, who owns which project, each week's lineup
 * and the PMs' task lists.
 *
 * The workbook upload wipes and reinserts staff and projects, so nothing here
 * is a foreign key to them: people are keyed by their staff name ("Julie") and
 * projects by job number ("#8113"). None of these tables is in the importer's
 * replaced list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('full_name')->nullable();
            // Links a Microsoft sign-in to its own PM Desk; stored lower-case.
            $table->string('email')->nullable()->unique();
            $table->string('tier')->default('staff');
            // Staff only: "tech" (the Technologists column) or "other".
            $table->string('grp')->nullable();
            // PMs only: the partner a week starts them under until someone moves them.
            $table->string('default_boss')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('project_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->index();
            $table->string('member_name');
            $table->string('role')->default('pm');
            $table->string('set_by');
            $table->timestamp('set_at');
            $table->unique(['job_number', 'member_name']);
        });

        Schema::create('pm_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->index();
            $table->string('title', 200);
            $table->string('assignee')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One row per week, keyed by its Monday. Placements map a person to the
        // person they go to, or "none" for a PM taken off for the week.
        Schema::create('lineup_weeks', function (Blueprint $table) {
            $table->date('week')->primary();
            $table->json('placements');
            $table->json('tags');
            $table->json('badges');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        (new TeamSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('lineup_weeks');
        Schema::dropIfExists('pm_tasks');
        Schema::dropIfExists('project_assignments');
        Schema::dropIfExists('team_members');
    }
};
