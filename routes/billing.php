<?php

use App\Enums\Permission;
use App\Http\Controllers\BillingReportsController;
use App\Livewire\Extras;
use App\Livewire\MonthEnd\MonthEndEntry;
use App\Livewire\MonthEnd\MonthEndReport;
use Illuminate\Support\Facades\Route;

// Loaded inside the authenticated group in routes/web.php.

Route::middleware('can:'.Permission::ViewFinancials->value)->group(function () {
    Route::get('month-end', MonthEndReport::class)->name('monthEnd');
    Route::get('month-end/entry/{period?}', MonthEndEntry::class)->name('monthEnd.entry');
    Route::get('month-end/{period}/print', [BillingReportsController::class, 'monthEnd'])->name('monthEnd.print');
    Route::get('reports/billing/{period}', [BillingReportsController::class, 'billing'])->name('reports.billing');

    Route::get('extras', Extras::class)->name('extras');
});
