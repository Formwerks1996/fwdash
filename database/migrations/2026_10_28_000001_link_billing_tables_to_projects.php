<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Every money row belongs to a project. Until now that was only true because
 * the importer skips unknown job numbers; the database now enforces it.
 *
 * A renumbered job carries its rows with it. A project that still has billing
 * cannot be deleted — the workbook import clears these tables first.
 *
 * project_statuses and the team tables stay unlinked on purpose: they outlive
 * a workbook import, which deletes and re-inserts projects.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    protected array $tables = [
        'project_phase_fees', 'project_rate_overrides', 'month_entries', 'hour_lines',
        'month_hourly', 'payments', 'consultant_fees', 'job_hours',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('job_number')
                    ->references('job_number')
                    ->on('projects')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['job_number']);
            });
        }
    }
};
