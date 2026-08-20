<?php

namespace App\Actions\Auth;

use App\Exceptions\Auth\EntraLoginFailedException;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Koppelt een Microsoft-profiel aan een bestaande desktop-user (admin/employee).
 * Geen JIT: onbekende e-mail = weigeren. Superuser en inactieve users: weigeren.
 */
class CompleteEntraLoginAction
{
    /**
     * @param  list<string>  $candidateEmails
     */
    public function handle(array $candidateEmails): User
    {
        $emails = [];
        foreach ($candidateEmails as $candidate) {
            $normalized = strtolower(trim($candidate));
            if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $emails[$normalized] = true;
        }

        $emails = array_keys($emails);

        if ($emails === []) {
            throw new EntraLoginFailedException('invalid_email');
        }

        $user = User::query()
            ->where(function (Builder $query) use ($emails): void {
                foreach ($emails as $email) {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->first();

        if ($user === null) {
            throw new EntraLoginFailedException('unknown');
        }

        if ($user->is_superuser || $user->tenant_id === null) {
            throw new EntraLoginFailedException('superuser');
        }

        if (! $user->is_active) {
            throw new EntraLoginFailedException('inactive');
        }

        return $user;
    }
}
