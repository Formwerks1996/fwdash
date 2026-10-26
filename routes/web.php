<?php

use App\Enums\Permission;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MicrosoftController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\WorkbookImportsController;
use App\Livewire\Hardware;
use App\Livewire\Invoicing;
use App\Livewire\Overview;
use App\Livewire\Projects\ProjectsIndex;
use App\Livewire\Projects\ProjectsShow;
use App\Livewire\Software;
use App\Livewire\Users;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('login', 'auth.login')->name('login');
    Route::get('auth/microsoft/redirect', [MicrosoftController::class, 'redirect'])->name('auth.microsoft.redirect');
    Route::get('auth/microsoft/callback', [MicrosoftController::class, 'callback'])->name('auth.microsoft.callback');
});

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::post('logout', LogoutController::class)->name('logout');

    Route::get('software', Software::class)->name('software');
    Route::get('hardware', Hardware::class)->name('hardware');

    Route::middleware('can:'.Permission::ViewFinancials->value)->group(function () {
        Route::get('overview', Overview::class)->name('overview');
        Route::get('invoicing', Invoicing::class)->name('invoicing');
        Route::get('projects', ProjectsIndex::class)->name('projects.index');
        Route::get('projects/{jobNumber}', ProjectsShow::class)->name('projects.show');
    });

    Route::middleware('can:'.Permission::ImportData->value)->group(function () {
        Route::get('imports', [WorkbookImportsController::class, 'create'])->name('imports.create');
        Route::post('imports', [WorkbookImportsController::class, 'store'])->name('imports.store');
    });

    Route::get('users', Users::class)
        ->middleware('can:'.Permission::ManageUsers->value)
        ->name('users');
});
