<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The invoicing workbook, as tables.
 *
 * Projects join on job number, never on a typed name. A billing period can be
 * closed, and nothing may be written to a closed period. Money is DECIMAL so
 * the engine can round the way Excel does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phases', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->unsignedSmallInteger('seq');
            $table->boolean('is_hourly')->default(false);
        });

        // Client-facing wording per phase code; RZP alone is written four ways.
        Schema::create('phase_descriptions', function (Blueprint $table) {
            $table->id();
            $table->string('phase_code');
            $table->string('description');
            $table->unique(['phase_code', 'description']);
        });

        Schema::create('rate_titles', function (Blueprint $table) {
            $table->string('title')->primary();
            $table->decimal('default_charge_out', 10, 2)->nullable();
            $table->unsignedSmallInteger('seq')->default(0);
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('rate_title')->nullable();
            $table->decimal('charge_out', 10, 2)->nullable();
            $table->string('status')->nullable();
            $table->date('start_date')->nullable();
            $table->date('finish_date')->nullable();
            $table->boolean('current')->default(true);
        });

        Schema::create('consultant_rates', function (Blueprint $table) {
            $table->id();
            $table->date('period');
            $table->string('consultant');
            $table->decimal('rate', 10, 2)->nullable();
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->unique(['period', 'consultant']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->string('job_number')->primary();
            $table->string('job_name')->unique();
            $table->string('developer')->nullable();
            $table->string('division')->nullable();
            $table->string('project_lead')->nullable();
            $table->boolean('active')->default(false);
            $table->boolean('andrew')->default(false);
            $table->decimal('ca_per_month', 14, 2)->nullable();
            $table->date('ifc_date')->nullable();
            $table->date('complete_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // A person's decision layered over projects.active: the only place a project can be Paused.
        Schema::create('project_statuses', function (Blueprint $table) {
            $table->string('job_number')->primary();
            $table->string('status');
            $table->text('note')->nullable();
            $table->string('set_by');
            $table->timestamp('set_at');
        });

        Schema::create('project_phase_fees', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->index();
            $table->string('phase_code');
            $table->decimal('fee', 14, 2)->nullable();
            $table->text('note')->nullable();
            $table->unique(['job_number', 'phase_code']);
        });

        Schema::create('project_rate_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->index();
            $table->string('rate_title');
            $table->decimal('rate', 10, 2);
            $table->unique(['job_number', 'rate_title']);
        });

        Schema::create('billing_periods', function (Blueprint $table) {
            $table->date('period')->primary();
            $table->string('status')->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_by')->nullable();
        });

        // A phase draw is a percentage of the fee OR a typed dollar figure (the CA monthly draw).
        // fee_at_entry freezes the fee as billed, so a contract amendment never restates an invoice.
        Schema::create('month_entries', function (Blueprint $table) {
            $table->id();
            $table->date('period');
            $table->string('job_number')->index();
            $table->string('phase_code');
            $table->decimal('pct_complete', 12, 8)->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->decimal('fee_at_entry', 14, 2)->nullable();
            $table->text('note')->nullable();
            $table->unique(['period', 'job_number', 'phase_code']);
        });

        // Time and materials. The rate is stored, not derived: rates change and the workbook kept no history.
        Schema::create('hour_lines', function (Blueprint $table) {
            $table->id();
            $table->date('period');
            $table->string('job_number');
            $table->string('staff')->nullable();
            $table->decimal('hours', 12, 4)->nullable();
            $table->decimal('rate', 10, 2)->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('rate_source')->nullable();
            $table->text('note')->nullable();
            $table->index(['period', 'job_number']);
        });

        // The hourly total for a job-month when it is not simply the sum of its lines.
        Schema::create('month_hourly', function (Blueprint $table) {
            $table->id();
            $table->date('period');
            $table->string('job_number');
            $table->decimal('amount', 14, 2);
            $table->text('note')->nullable();
            $table->unique(['period', 'job_number']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->date('paid_on');
            $table->string('job_number');
            $table->decimal('amount', 14, 2);
            $table->text('note')->nullable();
            $table->string('source')->nullable();
            $table->index(['job_number', 'paid_on']);
        });

        Schema::create('consultant_fees', function (Blueprint $table) {
            $table->id();
            $table->date('period');
            $table->string('job_number');
            $table->string('consultant')->nullable();
            $table->decimal('hours', 12, 4)->nullable();
            // Stored unrounded: hours x rate x FX, summed before rounding as the workbook does.
            $table->decimal('total', 18, 6)->nullable();
            $table->text('note')->nullable();
            $table->index(['job_number', 'period']);
        });

        // Hours logged per project per month. "prior" is the pre-system opening balance:
        // it counts toward a project's $/hr but never toward a month.
        Schema::create('job_hours', function (Blueprint $table) {
            $table->id();
            $table->date('period');
            $table->string('job_number');
            $table->decimal('hours', 12, 4);
            $table->string('source');
            $table->unique(['period', 'job_number', 'source']);
        });
    }

    public function down(): void
    {
        foreach ([
            'job_hours', 'consultant_fees', 'payments', 'month_hourly', 'hour_lines', 'month_entries',
            'billing_periods', 'project_rate_overrides', 'project_phase_fees', 'project_statuses', 'projects',
            'consultant_rates', 'staff', 'rate_titles', 'phase_descriptions', 'phases',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
