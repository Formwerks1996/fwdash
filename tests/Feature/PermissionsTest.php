<?php

use App\Models\User;

it('limits staff to the projects pages', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('home'))->assertRedirect(route('my-week'));
    $this->actingAs($staff)->get(route('projects.index'))->assertOk();
    $this->actingAs($staff)->get(route('alerts'))->assertOk();

    foreach (['overview', 'invoicing', 'software', 'hardware', 'lineup', 'imports.create', 'users'] as $route) {
        $this->actingAs($staff)->get(route($route))->assertForbidden();
    }
});

it('lets managers see the financial and office pages but not admin ones', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)->get(route('home'))->assertRedirect(route('overview'));

    foreach (['overview', 'invoicing', 'projects.index', 'software', 'hardware', 'lineup'] as $route) {
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

it('lets an admin preview the dashboard as staff and switch back', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('viewAs'), ['role' => 'staff'])
        ->assertRedirect(route('home'))
        ->assertSessionHas('view_as', 'staff');

    $this->withSession(['view_as' => 'staff']);

    $this->get(route('home'))->assertRedirect(route('my-week'));
    $this->get(route('projects.index'))->assertOk()->assertSee('View as')->assertDontSee('Add project');
    $this->get(route('overview'))->assertForbidden();
    $this->get(route('users'))->assertForbidden();

    $this->post(route('viewAs'), ['role' => 'admin'])->assertSessionMissing('view_as');
    $this->get(route('users'))->assertOk();
});

it('keeps the view-as toggle to admins', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->post(route('viewAs'), ['role' => 'admin'])->assertForbidden();
    $this->actingAs($staff)->get(route('projects.index'))->assertDontSee('View as');
});
