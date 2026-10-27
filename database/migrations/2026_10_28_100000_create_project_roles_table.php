<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Who leads and who manages each project, as Formwerks sets it, apart from
 * whatever Mosaic says (mosaic_project_roles). A lead is the partner over the
 * project; the project manager runs it under them. People are keyed by their
 * team_members name and projects by job number, with no foreign keys: the
 * workbook upload replaces projects.
 *
 * Starts with Andrew and Nikolas leading the projects the workbook gives them,
 * and Nathan managing 4855 Paton St.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_roles', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->index();
            $table->string('member_name')->index();
            // "lead" or "pm".
            $table->string('role');
            $table->string('set_by');
            $table->timestamp('set_at');
            $table->unique(['job_number', 'role', 'member_name']);
        });

        $now = now();

        $leads = DB::table('projects')
            ->whereIn('project_lead', ['Andrew', 'Nikolas'])
            ->get(['job_number', 'project_lead'])
            ->map(fn ($project) => [
                'job_number' => $project->job_number,
                'member_name' => $project->project_lead,
                'role' => 'lead',
                'set_by' => 'system',
                'set_at' => $now,
            ]);

        foreach ($leads->chunk(200) as $chunk) {
            DB::table('project_roles')->insert($chunk->all());
        }

        if (DB::table('projects')->where('job_number', '#1026')->exists()) {
            DB::table('project_roles')->insert([
                'job_number' => '#1026',
                'member_name' => 'Nathan',
                'role' => 'pm',
                'set_by' => 'system',
                'set_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_roles');
    }
};
