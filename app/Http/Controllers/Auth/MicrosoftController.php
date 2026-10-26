<?php

namespace App\Http\Controllers\Auth;

use App\Actions\SignInWithMicrosoftAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class MicrosoftController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('azure')->redirect();
    }

    public function callback(SignInWithMicrosoftAction $signIn): RedirectResponse
    {
        try {
            $microsoftUser = Socialite::driver('azure')->user();
        } catch (InvalidStateException) {
            return to_route('login')->withErrors(['microsoft' => __('Your sign-in expired. Please try again.')]);
        }

        $user = $signIn->execute($microsoftUser);

        if (! $user) {
            return to_route('login')->withErrors(['microsoft' => __('Only Formwerks accounts can sign in.')]);
        }

        Auth::login($user, remember: true);

        request()->session()->regenerate();

        return redirect()->intended(route('overview'));
    }
}
