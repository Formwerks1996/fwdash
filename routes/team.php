<?php

use App\Enums\Permission;
use App\Livewire\Team\Alerts;
use App\Livewire\Team\MyWeek;
use App\Livewire\Team\PmDesk;
use App\Livewire\Team\TeamLineup;
use Illuminate\Support\Facades\Route;

// Loaded inside the authenticated group in routes/web.php.

Route::get('lineup', TeamLineup::class)->middleware('can:'.Permission::ViewOffice->value)->name('lineup');

Route::get('my-week', MyWeek::class)->name('my-week');

Route::get('alerts', Alerts::class)->middleware('can:'.Permission::ViewAlerts->value)->name('alerts');

Route::get('pm-desk/{member?}', PmDesk::class)->middleware('can:'.Permission::ViewPmDesk->value)->name('pm-desk');
