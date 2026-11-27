<?php

namespace App\Actions\Auth;

use InvalidArgumentException;

/**
 * Een vingerafdruk meldt de computer niet aan. Het wachtwoord op de telefoon blijft verplicht.
 */
class VerifyUserPasskeyAssertionAction
{
    /**
     * @param  list<string>  $origins
     */
    public function handle(string $token, string $credentialJson, string $rpId, array $origins): void
    {
        throw new InvalidArgumentException('disabled');
    }
}
