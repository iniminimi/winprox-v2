<?php

declare(strict_types=1);

namespace App\Support\Marketing;

final class MunicipalPromoEmailBlockReason
{
    public const MISSING_EMAIL = 'missing_email';

    public const INVALID_EMAIL = 'invalid_email';

    public const MISSING_DOCX = 'missing_docx';

    public const MISSING_PROMO_RECIPIENT = 'missing_promo_recipient';

    public const ALREADY_SENT = 'already_sent';

    public const UNSUBSCRIBED = 'unsubscribed';
}
