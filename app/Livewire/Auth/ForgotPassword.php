<?php

namespace App\Livewire\Auth;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('WinProx')]
class ForgotPassword extends Component
{
    public string $email = '';

    public ?string $status = null;

    public function sendResetLink()
    {
        $request = new ForgotPasswordRequest;

        $this->validate($request->rules(), $request->messages());

        // Altijd dezelfde bevestiging tonen (geen e-mailadressen lekken).
        Password::sendResetLink(['email' => $this->email]);

        $this->status = __('auth.forgot.sent');
        $this->reset('email');
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
