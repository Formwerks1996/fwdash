<?php

use App\Enums\Permission;
use App\Livewire\Team\Alerts;
use App\Livewire\Team\MyWeek;
use App\Livewire\Team\TeamLineup;
use Illuminate\Support\Facades\Route;

// Loaded inside the authenticated group in routes/web.php.

Route::get('lineup', TeamLineup::class)->middleware('can:'.Permission::ViewOffice->value)->name('lineup');

Route::get('my-week', MyWeek::class)->name('my-week');

Route::get('alerts', Alerts::class)->middleware('can:'.Permission::ViewAlerts->value)->name('alerts');

// PM Desk is now the card view of Projects; keep old links working.
Route::get('pm-desk/{member?}', fn (?string $member = null) => to_route('projects.index', array_filter(['view' => 'cards', 'person' => $member])))->name('pm-desk');
