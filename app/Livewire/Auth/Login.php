<?php

namespace App\Livewire\Auth;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Auth;
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

    /** Bumps so the attention clip can refocus after each failed attempt. */
    public int $attentionTick = 0;

    public function login()
    {
        $request = new LoginRequest;

        try {
            $this->validate($request->rules(), $request->messages());
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->attentionTick++;

            throw $exception;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password, 'is_active' => true], $this->remember)) {
            $this->attentionTick++;
            $this->addError('email', __('auth.errors.failed'));

            return;
        }

        session()->regenerate();

        return $this->redirectRoute('dashboard', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->layoutData(['hideAuthLogo' => true]);
    }
}
