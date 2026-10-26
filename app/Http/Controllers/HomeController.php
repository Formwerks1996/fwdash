<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use Illuminate\Http\RedirectResponse;

class HomeController
{
    public function __invoke(): RedirectResponse
    {
        return request()->user()->can(Permission::ViewFinancials->value)
            ? to_route('overview')
            : to_route('software');
    }
}
