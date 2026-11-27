<?php

namespace App\Support\Auth;

use Illuminate\Validation\Rules\Password;

/**
 * Gedeelde wachtwoordregel voor elke deur waar een beheerwachtwoord wordt gezet.
 */
final class BackofficePassword
{
    public const MIN = 12;

    public static function rule(): Password
    {
        return Password::min(self::MIN)->uncompromised();
    }

    /**
     * @return list<mixed>
     */
    public static function constraints(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', self::rule()];
    }
}
