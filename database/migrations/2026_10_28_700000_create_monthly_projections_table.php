<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What the partners expect to bill and spend in a calendar month ("YYYY-MM"),
 * typed on Overview and set against what happens. Kept apart from the
 * workbook's tables, so an upload never touches it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_projections', function (Blueprint $table) {
            $table->id();
            $table->char('month', 7)->unique();
            $table->decimal('billed', 12, 2)->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('set_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_projections');
    }
};
