<?php

namespace App\Livewire\Auth;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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

    /** When true, only the attention clip is shown; form/errors stay out of the DOM. */
    public bool $attentionFocus = false;

    /** Remounts the attention clip after each failed attempt. */
    public int $attentionTick = 0;

    public function mount(): void
    {
        if (session()->has('error')) {
            $this->beginAttentionFocus();
        }
    }

    public function login()
    {
        $request = new LoginRequest;

        try {
            $this->validate($request->rules(), $request->messages());
        } catch (ValidationException $exception) {
            $this->beginAttentionFocus();

            throw $exception;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password, 'is_active' => true], $this->remember)) {
            $this->addError('email', __('auth.errors.failed'));
            $this->beginAttentionFocus();

            return;
        }

        session()->regenerate();

        return $this->redirectRoute('dashboard', navigate: false);
    }

    public function revealAfterAttention(): void
    {
        $this->attentionFocus = false;
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->layoutData(['hideAuthLogo' => true]);
    }

    private function beginAttentionFocus(): void
    {
        $this->attentionFocus = true;
        $this->attentionTick++;
        $this->js('setTimeout(() => $wire.revealAfterAttention(), 1000)');
    }
}
