<?php

namespace App\Livewire\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Support\Auth\EntraSso;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('WinProx')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $request = new LoginRequest;

        $this->validate($request->rules(), $request->messages());

        $throttleKey = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('email', __('auth.errors.throttle', ['seconds' => $seconds]));

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password, 'is_active' => true], $this->remember)) {
            RateLimiter::hit($throttleKey, 300);
            $this->addError('email', __('auth.errors.failed'));

            return;
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        return $this->redirectRoute('dashboard', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login', [
            'entraEnabled' => EntraSso::enabled(),
        ])->layoutData(['hideAuthLogo' => true, 'title' => __('auth.title').' — WinProx']);
    }
}
