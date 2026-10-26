<?php

use App\Models\User;

it('limits staff to the office pages', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('home'))->assertRedirect(route('software'));
    $this->actingAs($staff)->get(route('software'))->assertOk();
    $this->actingAs($staff)->get(route('hardware'))->assertOk();

    foreach (['overview', 'invoicing', 'projects.index', 'imports.create', 'users'] as $route) {
        $this->actingAs($staff)->get(route($route))->assertForbidden();
    }
});

it('lets managers see the financial pages but not admin ones', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)->get(route('home'))->assertRedirect(route('overview'));

    foreach (['overview', 'invoicing', 'projects.index'] as $route) {
        $this->actingAs($manager)->get(route($route))->assertOk();
    }

    $this->actingAs($manager)->get(route('imports.create'))->assertForbidden();
    $this->actingAs($manager)->get(route('users'))->assertForbidden();
});

it('lets admins do everything', function () {
    $admin = User::factory()->admin()->create();

    foreach (['overview', 'invoicing', 'projects.index', 'software', 'hardware', 'imports.create', 'users'] as $route) {
        $this->actingAs($admin)->get(route($route))->assertOk();
    }
});
