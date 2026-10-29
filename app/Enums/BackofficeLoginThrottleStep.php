<?php

namespace App\Enums;

/**
 * Stap in de begrenzing van mislukte beheerlogins.
 */
enum BackofficeLoginThrottleStep: string
{
    case Check = 'check';
    case Failure = 'failure';
    case Success = 'success';
}
