<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\ConfirmPhoneLoginAction;
use App\Actions\Auth\ShowPhoneLoginChallengeAction;
use App\Actions\Auth\SubmitPhonePasswordAction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('WinProx')]
class PhoneLogin extends Component
{
    public string $token = '';

    public string $password = '';

    public bool $ready = false;

    public string $code = '';

    public string $computer = '';

    public string $organization = '';

    public string $status = '';

    public bool $hasPasskey = false;

    public bool $canRegister = false;

    public function mount(string $token, ShowPhoneLoginChallengeAction $show): void
    {
        $this->token = $token;
        $this->loadChallenge($show);
    }

    public function submitPassword(SubmitPhonePasswordAction $submit, ShowPhoneLoginChallengeAction $show): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => __('auth.errors.password_required'),
        ]);

        $result = $submit->handle($this->token, $this->password, (string) request()->ip());
        $this->password = '';

        if (! ($result['ok'] ?? false)) {
            $this->addError('password', __('auth.phone.errors.'.($result['error'] ?? 'failed')));

            return;
        }

        $this->loadChallenge($show);
    }

    public function confirm(ConfirmPhoneLoginAction $confirm, ShowPhoneLoginChallengeAction $show): void
    {
        $result = $confirm->handle($this->token);
        if (! ($result['ok'] ?? false)) {
            $this->addError('password', __('auth.phone.errors.'.($result['error'] ?? 'failed')));

            return;
        }

        $this->loadChallenge($show);
    }

    public function render()
    {
        return view('livewire.auth.phone-login')->layoutData([
            'hideAuthLogo' => true,
            'title' => __('auth.phone.title').' — WinProx',
        ]);
    }

    private function loadChallenge(ShowPhoneLoginChallengeAction $show): void
    {
        $data = $show->handle($this->token);
        $this->ready = $data !== null;
        if ($data === null) {
            return;
        }

        $this->code = (string) $data['code'];
        $this->computer = (string) $data['computer'];
        $this->organization = (string) $data['organization'];
        $this->status = (string) $data['status'];
        $this->hasPasskey = (bool) $data['has_passkey'];
        $this->canRegister = (bool) $data['can_register'];
    }
}
