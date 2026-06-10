<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\ResetUserPasswordAction;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('WinProx')]
class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request('email', '');
    }

    public function resetPassword()
    {
        $request = new ResetPasswordRequest;

        $this->validate($request->rules(), $request->messages());

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user) {
                app(ResetUserPasswordAction::class)->handle($user, $this->password);

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            $this->addError('email', __('auth.reset.failed'));

            return;
        }

        session()->flash('status', __('auth.reset.success'));

        return $this->redirectRoute('login', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
