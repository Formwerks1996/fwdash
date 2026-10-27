<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One row per project: the figures the billing engine works out from the
 * source tables (fees, Month End, hours, payments, consultants), stored so
 * Supabase and anything reading it sees a project's financials in one place.
 *
 * It is derived, never typed into. The workbook import and every billing edit
 * rewrite the affected rows; the source tables stay the truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_financials', function (Blueprint $table) {
            $table->string('job_number')->primary();
            $table->foreign('job_number')->references('job_number')->on('projects')->cascadeOnUpdate()->cascadeOnDelete();
            $table->decimal('contract', 14, 2);
            $table->decimal('billed', 14, 2);
            $table->decimal('phase_billed', 14, 2);
            $table->decimal('hourly_billed', 14, 2);
            $table->decimal('billed_pct', 10, 6)->nullable();
            $table->json('billed_by_phase');
            $table->decimal('balance', 14, 2);
            $table->decimal('balance_design', 14, 2);
            $table->decimal('balance_ca', 14, 2);
            $table->decimal('payments_total', 14, 2);
            $table->decimal('outstanding', 14, 2);
            $table->date('last_payment_date')->nullable();
            $table->decimal('last_payment_amount', 14, 2)->nullable();
            $table->decimal('consultant_total', 14, 2);
            $table->decimal('hours_total', 12, 2);
            $table->decimal('opening_hours', 12, 2)->nullable();
            $table->decimal('rate_per_hour', 10, 2)->nullable();
            $table->timestamp('refreshed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_financials');
    }
};
