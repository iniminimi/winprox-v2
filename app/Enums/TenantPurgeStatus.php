<?php

namespace App\Enums;

enum TenantPurgeStatus: string
{
    /** Wacht op bevestiging via e-mail-link. */
    case AwaitingEmail = 'awaiting_email';

    /** Trial: e-mail bevestigd, admin mag uitvoeren. */
    case Ready = 'ready';

    /** Betaald: gepland na cool-down; alleen superuser voert uit. */
    case Scheduled = 'scheduled';

    case Cancelled = 'cancelled';

    case Completed = 'completed';
}
