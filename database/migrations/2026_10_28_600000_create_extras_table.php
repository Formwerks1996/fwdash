<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Extras: work on a job beyond its contract phases, agreed at a set amount or
 * a set number of hours, or open-ended hourly work such as construction
 * administration. Every hourly billing line is tagged to one. Projects are
 * keyed by job number with no foreign key: the workbook upload replaces them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extras', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->index();
            // "8040-04" for an extra Mosaic numbered; null otherwise.
            $table->string('code')->nullable();
            $table->string('title');
            // proposed, active, complete or rejected.
            $table->string('status', 20)->default('proposed');
            $table->decimal('budget_amount', 12, 2)->nullable();
            $table->decimal('budget_hours', 10, 2)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('mosaic_id')->nullable()->unique();
            // The Mosaic phase name, to find the hours logged on it.
            $table->string('mosaic_phase')->nullable();
            $table->timestamps();
        });

        Schema::table('hour_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('extra_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('hour_lines', function (Blueprint $table) {
            $table->dropIndex(['extra_id']);
            $table->dropColumn('extra_id');
        });

        Schema::dropIfExists('extras');
    }
};
