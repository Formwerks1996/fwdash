<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A revenue target per calendar month. Not part of the workbook export, so it
 * is never touched by an import and must be backed up on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_targets', function (Blueprint $table) {
            $table->char('month', 7)->primary();
            $table->decimal('amount', 14, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_targets');
    }
};
