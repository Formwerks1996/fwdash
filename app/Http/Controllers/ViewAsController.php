<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/*
 * The header toggle that lets an admin see the dashboard as another role
 * would. Checks the stored role rather than a permission, because while a
 * preview is on the admin's permissions are the previewed role's.
 */
class ViewAsController
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole(Role::Admin->value), 403);

        $role = Role::from($request->validate(['role' => ['required', Rule::enum(Role::class)]])['role']);

        $role === Role::Admin
            ? $request->session()->forget('view_as')
            : $request->session()->put('view_as', $role->value);

        return to_route('home');
    }
}
