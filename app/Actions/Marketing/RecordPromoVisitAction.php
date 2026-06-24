<?php

namespace App\Actions\Marketing;

use App\Models\PromoVisit;
use Carbon\CarbonInterface;

class RecordPromoVisitAction
{
    private const RECIPIENT_DEDUPE_MINUTES = 2;

    /**
     * @return PromoVisit|null Null when dit een herhaalde hit is binnen het dedupe-venster.
     */
    public function handle(?int $promoRecipientId, string $locale, CarbonInterface $visitedAt): ?PromoVisit
    {
        if ($promoRecipientId !== null) {
            $existing = PromoVisit::query()
                ->where('promo_recipient_id', $promoRecipientId)
                ->where('visited_at', '>=', $visitedAt->copy()->subMinutes(self::RECIPIENT_DEDUPE_MINUTES))
                ->latest('visited_at')
                ->first();

            if ($existing !== null) {
                return null;
            }
        }

        return PromoVisit::query()->create([
            'promo_recipient_id' => $promoRecipientId,
            'locale' => $locale,
            'visited_at' => $visitedAt,
        ]);
    }
}
