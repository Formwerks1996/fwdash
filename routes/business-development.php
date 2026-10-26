<?php

use App\Enums\Permission;
use App\Http\Controllers\OutreachImagesController;
use App\Livewire\BusinessDevelopment\DevelopersIndex;
use App\Livewire\BusinessDevelopment\DevelopersShow;
use App\Livewire\BusinessDevelopment\OutreachIndex;
use App\Livewire\BusinessDevelopment\OutreachShow;
use App\Livewire\BusinessDevelopment\Pipeline;
use Illuminate\Support\Facades\Route;

// Loaded inside the authenticated group in routes/web.php.

Route::middleware('can:'.Permission::ViewBusinessDevelopment->value)->group(function () {
    Route::get('pipeline', Pipeline::class)->name('pipeline');
    Route::get('developers', DevelopersIndex::class)->name('developers.index');
    Route::get('developers/{developerKey}', DevelopersShow::class)->name('developers.show');
    Route::get('outreach', OutreachIndex::class)->name('outreach.index');
    Route::get('outreach/{prospectKey}', OutreachShow::class)->name('outreach.show');
    Route::get('outreach-images/{name}', [OutreachImagesController::class, 'show'])
        ->where('name', '[A-Za-z0-9._-]+')
        ->name('outreach.images');
});
