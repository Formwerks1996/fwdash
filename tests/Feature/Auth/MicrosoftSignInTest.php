<?php

use App\Enums\Role;
use App\Models\User;
use App\Notifications\NewUserSignedInNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeMicrosoftUser(string $email, string $id = 'ms-123', string $name = 'Pat Example'): void
{
    $microsoftUser = (new SocialiteUser)->map(['id' => $id, 'name' => $name, 'email' => $email]);

    Socialite::shouldReceive('driver->user')->andReturn($microsoftUser);
}

it('sends guests to the login page', function () {
    $this->get(route('overview'))->assertRedirect(route('login'));
});

it('shows the Microsoft sign-in button', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in with Microsoft');
});

it('creates new people as staff and tells the admins', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();

    fakeMicrosoftUser('pat@formwerks.ca');

    $this->get(route('auth.microsoft.callback'))->assertRedirect();

    $user = User::where('email', 'pat@formwerks.ca')->sole();

    expect($user->microsoft_id)->toBe('ms-123')
        ->and($user->primaryRole())->toBe(Role::Staff);

    $this->assertAuthenticatedAs($user);

    Notification::assertSentTo($admin, NewUserSignedInNotification::class, fn ($notification) => $notification->newUser->is($user));
});

it('does not tell the admins again on later sign-ins', function () {
    Notification::fake();

    User::factory()->admin()->create();
    User::factory()->staff()->create(['email' => 'pat@formwerks.ca', 'microsoft_id' => 'ms-123']);

    fakeMicrosoftUser('pat@formwerks.ca');

    $this->get(route('auth.microsoft.callback'));

    Notification::assertNothingSent();
});

it('keeps the seeded admin an admin when they first sign in', function () {
    $this->seed();

    fakeMicrosoftUser('Nathan@Formwerks.ca', 'ms-nathan', 'Nathan');

    $this->get(route('auth.microsoft.callback'));

    $nathan = User::where('email', 'nathan@formwerks.ca')->sole();

    expect($nathan->primaryRole())->toBe(Role::Admin)
        ->and($nathan->microsoft_id)->toBe('ms-nathan')
        ->and(User::count())->toBe(1);
});

it('refuses accounts outside the allowed domains', function () {
    fakeMicrosoftUser('someone@gmail.com');

    $this->get(route('auth.microsoft.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('microsoft');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('signs out', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('does not send people to Microsoft when no app is registered', function () {
    config(['services.azure.client_id' => null]);

    $this->get(route('auth.microsoft.redirect'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('microsoft');
});
