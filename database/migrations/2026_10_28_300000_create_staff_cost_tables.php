<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What each person costs the firm an hour, dated so a raise never reprices the
 * months before it, and the hours each person logged per month. Mosaic's pull
 * seeds both; the dashboard's own timesheets take over the hours after it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_cost_rates', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name')->nullable();
            $table->decimal('hourly_cost', 10, 2);
            $table->date('starts_on');
            $table->timestamps();

            $table->unique(['email', 'starts_on']);
        });

        Schema::create('staff_month_hours', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name')->nullable();
            $table->char('month', 7)->index();
            $table->decimal('hours', 10, 2);
            $table->string('source', 20);

            $table->unique(['email', 'month', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_month_hours');
        Schema::dropIfExists('staff_cost_rates');
    }
};
