<?php

use App\Enums\Permission;
use App\Livewire\Team\Alerts;
use App\Livewire\Team\PmDesk;
use App\Livewire\Team\TeamLineup;
use Illuminate\Support\Facades\Route;

// Loaded inside the authenticated group in routes/web.php.

Route::get('lineup', TeamLineup::class)->name('lineup');

Route::middleware('can:'.Permission::ViewFinancials->value)->group(function () {
    Route::get('alerts', Alerts::class)->name('alerts');
    Route::get('pm-desk/{member?}', PmDesk::class)->name('pm-desk');
});
