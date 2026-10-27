<?php

use App\Enums\Permission;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MicrosoftController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\QuickJumpController;
use App\Http\Controllers\ViewAsController;
use App\Http\Controllers\WorkbookImportsController;
use App\Livewire\Hardware;
use App\Livewire\Invoicing;
use App\Livewire\Overview;
use App\Livewire\Projects\ProjectRoles;
use App\Livewire\Projects\ProjectsCreate;
use App\Livewire\Projects\ProjectsIndex;
use App\Livewire\Projects\ProjectsShow;
use App\Livewire\Software;
use App\Livewire\StaffCosts;
use App\Livewire\Timesheets\Timesheet;
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
    Route::post('view-as', ViewAsController::class)->name('viewAs');
    Route::view('help', 'help.show')->name('help');
    Route::get('quick-jump', QuickJumpController::class)->name('quickJump');

    Route::middleware('can:'.Permission::ViewOffice->value)->group(function () {
        Route::get('software', Software::class)->name('software');
        Route::get('hardware', Hardware::class)->name('hardware');
    });

    Route::get('staff-costs', StaffCosts::class)
        ->middleware('can:'.Permission::ManageStaffCosts->value)
        ->name('staff-costs');

    Route::get('projects/leads-and-pms', ProjectRoles::class)
        ->middleware('can:'.Permission::EditTeam->value)
        ->name('project-roles');

    Route::middleware('can:'.Permission::ViewProjects->value)->group(function () {
        Route::get('projects', ProjectsIndex::class)->name('projects.index');
        Route::get('projects/new', ProjectsCreate::class)
            ->middleware('can:'.Permission::EditBilling->value)
            ->name('projects.create');
        Route::get('projects/{jobNumber}', ProjectsShow::class)->name('projects.show');
    });

    Route::get('timesheets', Timesheet::class)
        ->middleware('can:'.Permission::ViewTimesheets->value)
        ->name('timesheets');

    Route::middleware('can:'.Permission::ViewFinancials->value)->group(function () {
        Route::get('overview', Overview::class)->name('overview');
        Route::get('invoicing', Invoicing::class)->name('invoicing');
    });

    Route::middleware('can:'.Permission::ImportData->value)->group(function () {
        Route::get('imports', [WorkbookImportsController::class, 'create'])->name('imports.create');
        Route::post('imports', [WorkbookImportsController::class, 'store'])->name('imports.store');
    });

    require __DIR__.'/billing.php';
    require __DIR__.'/team.php';
    require __DIR__.'/business-development.php';

    Route::get('users', Users::class)
        ->middleware('can:'.Permission::ManageUsers->value)
        ->name('users');
});
