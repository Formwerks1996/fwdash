<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Hours logged on each phase of a job, and what they come to at each person's
 * charge-out rate, from a Mosaic pull. Kept under Mosaic's phase name so a
 * name that matches no billing phase still counts toward the job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_phase_hours', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->index();
            $table->string('mosaic_phase');
            $table->string('phase_code')->nullable();
            $table->decimal('hours', 10, 2);
            $table->decimal('charge_out', 12, 2);
            $table->decimal('unrated_hours', 10, 2)->default(0);

            $table->unique(['job_number', 'mosaic_phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_phase_hours');
    }
};
