<?php

namespace App\Actions\Marketing;

use App\Models\PromoVisit;
use Carbon\CarbonInterface;

class RecordPromoVisitAction
{
    public function handle(?int $promoRecipientId, string $locale, CarbonInterface $visitedAt): PromoVisit
    {
        return PromoVisit::query()->create([
            'promo_recipient_id' => $promoRecipientId,
            'locale' => $locale,
            'visited_at' => $visitedAt,
        ]);
    }
}
