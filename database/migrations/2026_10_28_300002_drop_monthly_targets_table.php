<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Monthly Targets was retired: Month End's revenue chart now sets billing
 * against what the month cost instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('monthly_targets');
    }

    public function down(): void
    {
        Schema::create('monthly_targets', function (Blueprint $table) {
            $table->char('month', 7)->primary();
            $table->decimal('amount', 14, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }
};
