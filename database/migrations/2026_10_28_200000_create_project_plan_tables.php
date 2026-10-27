<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * How a job is set up when it is created, the way Formwerks set jobs up in
 * Mosaic: its own phases (named as the contract names them, each billed under
 * one of the fixed billing phases), the hours budgeted per phase and per
 * person, and work plans booking people onto phases.
 *
 * Keyed by job number with no foreign key to projects, like the team tables:
 * the workbook upload deletes and re-inserts projects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_phases', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->index();
            $table->unsignedSmallInteger('seq');
            $table->string('name');
            // A Phase code, or "Hours" for a time-and-materials phase.
            $table->string('billing_code');
            $table->decimal('fee', 14, 2)->nullable();
            $table->decimal('estimated_hours', 10, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('project_hour_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_phase_id')->constrained()->cascadeOnDelete();
            $table->string('member_name');
            $table->decimal('hours', 10, 2);
            $table->unique(['project_phase_id', 'member_name']);
        });

        Schema::create('project_work_plans', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->index();
            $table->foreignId('project_phase_id')->nullable()->constrained()->nullOnDelete();
            $table->string('member_name')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('daily_hours', 4, 2);
            $table->timestamps();
        });

        Schema::table('project_assignments', function (Blueprint $table) {
            // What they do on the job, e.g. "Technologist", as Mosaic's project roles had it.
            $table->string('position')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('project_assignments', function (Blueprint $table) {
            $table->dropColumn('position');
        });

        Schema::dropIfExists('project_work_plans');
        Schema::dropIfExists('project_hour_budgets');
        Schema::dropIfExists('project_phases');
    }
};
