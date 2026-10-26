<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
 * Local development only: sign in as an existing user without Microsoft.
 * Loaded from bootstrap/app.php when APP_ENV=local, and refused to anything
 * but this machine.
 */
Route::get('dev/login/{email}', function (string $email) {
    abort_unless(in_array(request()->ip(), ['127.0.0.1', '::1'], true), 404);

    Auth::login(User::where('email', $email)->firstOrFail());

    request()->session()->regenerate();

    return to_route('home');
})->name('dev.login');
