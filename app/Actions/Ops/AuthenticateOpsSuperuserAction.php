<?php

declare(strict_types=1);

namespace App\Actions\Ops;

use App\Actions\Audit\LogAuditAction;
use App\Actions\Auth\ThrottleBackofficeLoginAction;
use App\Enums\BackofficeLoginThrottleStep;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Superuser-login voor WinProx OPS op de gsm (buiten /login + desktop-middleware).
 */
final class AuthenticateOpsSuperuserAction
{
    public function __construct(
        private ThrottleBackofficeLoginAction $throttle,
        private LogAuditAction $audit,
    ) {}

    public function handle(string $email, string $password, string $ip): User
    {
        $email = strtolower(trim($email));

        $seconds = $this->throttle->handle($email, $ip, BackofficeLoginThrottleStep::Check);
        if ($seconds !== null) {
            throw new InvalidArgumentException('throttle:'.$seconds);
        }

        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            $this->throttle->handle($email, $ip, BackofficeLoginThrottleStep::Failure);
            throw new InvalidArgumentException('auth_failed');
        }

        if (! $user->is_superuser) {
            $this->throttle->handle($email, $ip, BackofficeLoginThrottleStep::Failure);
            throw new InvalidArgumentException('not_superuser');
        }

        $this->throttle->handle($email, $ip, BackofficeLoginThrottleStep::Success);
        Auth::login($user, remember: true);

        $this->audit->handle(
            userId: (int) $user->id,
            tenantId: null,
            action: 'ops.portal_login',
            modelType: User::class,
            modelId: (int) $user->id,
            payload: ['ip' => $ip],
        );

        return $user;
    }
}
