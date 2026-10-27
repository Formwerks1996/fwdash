<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One row per cell of a person's weekly timesheet: a day, what the time was
 * for, the hours and a note. job_number has no foreign key on purpose: the
 * workbook upload deletes and reinserts projects, which a key would block.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('category', 30)->default('project');
            $table->string('job_number')->nullable()->index();
            $table->string('phase_code')->nullable();
            $table->decimal('hours', 5, 2);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'work_date']);
            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_entries');
    }
};
