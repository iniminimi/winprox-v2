<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Models\PromoCampaignTarget;
use App\Models\PromoRecipient;

final class PromoRecipientLocale
{
    public static function forRecipient(PromoRecipient $recipient): ?string
    {
        $raw = PromoCampaignTarget::query()
            ->where('promo_recipient_id', $recipient->id)
            ->whereHas('campaign')
            ->with('campaign:id,locale')
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->first()
            ?->campaign
            ?->locale;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $locale = strtolower(trim($raw));
        $supported = config('locales.supported', []);

        return in_array($locale, $supported, true) ? $locale : null;
    }
}
