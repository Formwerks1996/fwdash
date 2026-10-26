<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Price is stored as billed (amount + cycle), not pre-annualised, and currency is never assumed.
        Schema::create('software', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('division')->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->string('currency', 3)->default('CAD');
            $table->string('cycle')->default('Monthly');
            $table->unsignedInteger('seats')->nullable();
            $table->unsignedInteger('seats_used')->nullable();
            $table->text('description')->nullable();
            $table->text('note')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('hardware', function (Blueprint $table) {
            $table->id();
            $table->string('cid')->nullable();
            $table->string('user_name')->nullable();
            $table->string('name_future')->nullable();
            $table->string('dept')->nullable();
            $table->unsignedSmallInteger('replace_year')->nullable();
            $table->string('override')->nullable();
            $table->string('upgrade')->nullable();
            $table->string('sophos', 1)->nullable();
            $table->string('location')->nullable();
            $table->string('ip_address')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->string('serial')->nullable();
            $table->string('model')->nullable();
            $table->string('screen_size')->nullable();
            $table->string('os_version')->nullable();
            $table->string('cpu')->nullable();
            $table->string('cpu_speed')->nullable();
            $table->unsignedSmallInteger('cpu_cores')->nullable();
            $table->string('memory')->nullable();
            $table->string('mem_type')->nullable();
            $table->string('mem_banks')->nullable();
            $table->string('graphics')->nullable();
            $table->string('hd1')->nullable();
            $table->string('hd2')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('retired')->default(false);
            $table->timestamps();
        });

        // Figures that belong to the dashboard rather than a record, e.g. pinned FX rates.
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor');
            $table->string('action');
            $table->string('table_name');
            $table->string('record_key')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->index();
        });

        Schema::create('workbook_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->json('counts');
            $table->json('warnings');
            $table->json('reconciliation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workbook_imports');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('hardware');
        Schema::dropIfExists('software');
    }
};
