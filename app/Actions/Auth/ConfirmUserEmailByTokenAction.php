<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class ConfirmUserEmailByTokenAction
{
    public function __construct(private MarkUserEmailVerifiedAction $markVerified) {}

    public function handle(string $token): User
    {
        $normalized = strtolower(trim($token));
        if ($normalized === '' || ! preg_match('/^[a-z0-9]{20,64}$/', $normalized)) {
            throw ValidationException::withMessages([
                'token' => [__('auth.verify.link_invalid')],
            ]);
        }

        $user = User::query()
            ->where('email_verify_token', hash('sha256', $normalized))
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'token' => [__('auth.verify.link_invalid')],
            ]);
        }

        if ($user->email_verify_expires_at === null || $user->email_verify_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => [__('auth.verify.link_expired')],
            ]);
        }

        return $this->markVerified->handle($user);
    }
}
