<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CompleteEntraLoginAction;
use App\Exceptions\Auth\EntraLoginFailedException;
use App\Http\Controllers\Controller;
use App\Support\Auth\EntraSso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class MicrosoftAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! EntraSso::enabled()) {
            abort(404);
        }

        return Socialite::driver('azure')->redirect();
    }

    public function callback(CompleteEntraLoginAction $complete): RedirectResponse
    {
        if (! EntraSso::enabled()) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver('azure')->user();
        } catch (\Throwable) {
            return redirect()
                ->route('login')
                ->with('error', __('auth.errors.microsoft_failed'));
        }

        try {
            $user = $complete->handle(EntraSso::candidateEmails($socialUser));
        } catch (EntraLoginFailedException) {
            return redirect()
                ->route('login')
                ->with('error', __('auth.errors.microsoft_failed'));
        }

        Auth::login($user);
        session()->regenerate();

        return redirect()->route('dashboard');
    }
}
