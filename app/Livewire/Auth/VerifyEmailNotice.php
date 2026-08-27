<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\SendUserEmailVerificationAction;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('WinProx')]
class VerifyEmailNotice extends Component
{
    public ?string $status = null;

    public bool $showWelcome = false;

    public function mount(): void
    {
        $this->showWelcome = (bool) session('register_success');

        $user = auth()->user();

        if ($user instanceof User && $user->hasVerifiedEmail()) {
            $this->redirectRoute('dashboard', navigate: false);
        }
    }

    public function resend(SendUserEmailVerificationAction $sendVerification): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $result = $sendVerification->handle($user);

        $this->status = $result['sent']
            ? __('auth.verify.resent')
            : __('auth.verify.throttled', ['minutes' => (int) ceil($result['retry_after'] / 60)]);
    }

    public function render()
    {
        return view('livewire.auth.verify-email-notice', [
            'email' => (string) (auth()->user()?->email ?? ''),
        ]);
    }
}
