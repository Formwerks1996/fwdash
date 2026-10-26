<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class Navigation
{
    /**
     * The pages a user can open, grouped as in the sidebar.
     *
     * @return array<string, array<int, array{route: string, icon: string, label: string, permission: ?string, editable: bool, help: string}>>
     */
    public static function for(?User $user): array
    {
        return collect(config()->array('navigation'))
            ->map(fn (array $pages) => array_values(array_filter(
                $pages,
                fn (array $page) => Route::has($page['route'])
                    && ($page['permission'] === null || $user?->can($page['permission'])),
            )))
            ->filter()
            ->all();
    }
}
