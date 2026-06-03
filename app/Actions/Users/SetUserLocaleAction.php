<?php

namespace App\Actions\Users;

use App\Models\User;

class SetUserLocaleAction
{
    public function handle(User $user, string $locale): void
    {
        if (! in_array($locale, config('locales.supported', []), true)) {
            throw new \InvalidArgumentException('unsupported_locale');
        }

        if ($user->locale === $locale) {
            return;
        }

        $user->forceFill(['locale' => $locale])->save();
    }
}
