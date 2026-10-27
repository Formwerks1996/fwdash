<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What the firm spent each calendar month, as the bookkeeper totals it, one
 * line per expense column of the workbook's Costs sheet (Payroll, Rent,
 * Software…). Overview sets it against billing to show profit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_costs', function (Blueprint $table) {
            $table->id();
            $table->char('month', 7);
            $table->string('category');
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('seq')->default(0);

            $table->unique(['month', 'category']);
        });

        Schema::create('monthly_cost_notes', function (Blueprint $table) {
            $table->char('month', 7)->primary();
            $table->string('note');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_cost_notes');
        Schema::dropIfExists('monthly_costs');
    }
};
