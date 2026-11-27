<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\ConsumeBackofficeLoginChallengeAction;
use App\Actions\Auth\StartBackofficeLoginChallengeAction;
use App\Actions\Auth\ThrottleBackofficeLoginAction;
use App\Enums\BackofficeLoginThrottleStep;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\Auth\EntraSso;
use App\Support\Http\PhoneUserAgent;
use App\Support\Qr\QrSvg;
use BaconQrCode\Common\ErrorCorrectionLevel;
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

    public ?string $phoneLoginToken = null;

    public ?string $phoneLoginCode = null;

    public ?string $phoneLoginExpiresAt = null;

    public function mount(StartBackofficeLoginChallengeAction $start): void
    {
        $this->refreshPhoneChallenge($start, false);
    }

    public function pollPhoneLogin(
        ConsumeBackofficeLoginChallengeAction $consume,
        StartBackofficeLoginChallengeAction $start,
    ) {
        if ($this->phoneLoginExpiresAt !== null && now()->greaterThan($this->phoneLoginExpiresAt)) {
            $this->refreshPhoneChallenge($start, true);

            return;
        }

        $user = $consume->handle(session()->getId());
        if ($user === null) {
            return;
        }

        Auth::login($user);
        session()->regenerate();

        return $this->redirectRoute('dashboard', navigate: false);
    }

    public function login()
    {
        if (PhoneUserAgent::isPhone(request()->userAgent())) {
            return $this->redirectRoute('login', navigate: false);
        }

        $request = new LoginRequest;

        $this->validate($request->rules(), $request->messages());

        $throttle = app(ThrottleBackofficeLoginAction::class);
        $ip = (string) request()->ip();

        $seconds = $throttle->handle($this->email, $ip, BackofficeLoginThrottleStep::Check);
        if ($seconds !== null) {
            $this->addError('email', __('auth.errors.throttle', ['seconds' => $seconds]));

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password, 'is_active' => true], $this->remember)) {
            $throttle->handle($this->email, $ip, BackofficeLoginThrottleStep::Failure);
            $this->addError('email', __('auth.errors.failed'));

            return;
        }

        $throttle->handle($this->email, $ip, BackofficeLoginThrottleStep::Success);
        session()->regenerate();

        return $this->redirectRoute('dashboard', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login', [
            'entraEnabled' => EntraSso::enabled(),
            'phoneLoginSvg' => $this->phoneLoginToken !== null
                ? QrSvg::svg(route('login.phone', ['token' => $this->phoneLoginToken]), 96, ErrorCorrectionLevel::L(), 2)
                : null,
        ])->layoutData(['hideAuthLogo' => true, 'title' => __('auth.title').' — WinProx']);
    }

    private function refreshPhoneChallenge(StartBackofficeLoginChallengeAction $start, bool $forceNew): void
    {
        $result = $start->handle(
            session()->getId(),
            (string) request()->userAgent(),
            $forceNew ? null : $this->phoneLoginToken,
        );

        if ($result === null) {
            $this->phoneLoginToken = null;
            $this->phoneLoginCode = null;
            $this->phoneLoginExpiresAt = null;

            return;
        }

        $this->phoneLoginToken = $result['token'];
        $this->phoneLoginCode = $result['code'];
        $this->phoneLoginExpiresAt = $result['expires_at'];
    }
}
