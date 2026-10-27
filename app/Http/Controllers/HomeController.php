<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use Illuminate\Http\RedirectResponse;

class HomeController
{
    public function __invoke(): RedirectResponse
    {
        $user = request()->user();

        return match (true) {
            $user->can(Permission::ViewFinancials->value) => to_route('overview'),
            default => to_route('my-week'),
        };
    }
}
