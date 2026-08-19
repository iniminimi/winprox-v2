<?php

namespace App\Enums;

enum TenantPurgeTrack: string
{
    case Trial = 'trial';
    case Paid = 'paid';

    /** Automatische purge na verlopen proef zonder abonnement. */
    case ExpiredTrial = 'expired_trial';

    /** Nooit gebruikt account: e-mail niet geverifieerd, of door superuser als spam gemarkeerd. */
    case Unused = 'unused';
}
