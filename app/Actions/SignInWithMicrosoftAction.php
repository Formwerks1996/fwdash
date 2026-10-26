<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\NewUserSignedInNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class SignInWithMicrosoftAction
{
    /** Finds or creates the user for a Microsoft account. New users start as staff. */
    public function execute(SocialiteUser $microsoftUser): ?User
    {
        $email = Str::lower($microsoftUser->getEmail() ?? $microsoftUser->user['userPrincipalName'] ?? '');

        if (! $this->isAllowed($email)) {
            return null;
        }

        $user = User::where('microsoft_id', $microsoftUser->getId())->first()
            ?? User::firstOrNew(['email' => $email]);

        $user->fill([
            'name' => $microsoftUser->getName() ?: ($user->name ?? $email),
            'email' => $email,
            'microsoft_id' => $microsoftUser->getId(),
            'last_login_at' => now(),
        ])->save();

        if ($user->roles()->doesntExist()) {
            $user->assignRole(Role::Staff->value);

            Notification::send(User::role(Role::Admin->value)->get(), new NewUserSignedInNotification($user));
        }

        return $user;
    }

    protected function isAllowed(string $email): bool
    {
        if ($email === '') {
            return false;
        }

        $domains = config('services.azure.allowed_domains');

        if (empty($domains)) {
            return true;
        }

        return in_array(Str::after($email, '@'), $domains, true);
    }
}
