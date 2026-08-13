<?php

namespace App\Actions\Marketing;

use App\Enums\PromoVisitFollowKey;
use App\Enums\PromoVisitKind;
use App\Enums\PromoVisitPage;
use App\Models\PromoVisit;
use Carbon\CarbonInterface;

class RecordPromoVisitAction
{
    private const RECIPIENT_DEDUPE_MINUTES = 2;

    /**
     * @return PromoVisit|null Null when dit een herhaalde hit is binnen het dedupe-venster.
     */
    public function handle(
        ?int $promoRecipientId,
        string $locale,
        CarbonInterface $visitedAt,
        PromoVisitPage $page = PromoVisitPage::Promo,
        PromoVisitKind $kind = PromoVisitKind::Hit,
        ?PromoVisitFollowKey $followKey = null,
    ): ?PromoVisit {
        if ($kind === PromoVisitKind::Follow && $followKey === null) {
            return null;
        }

        if ($promoRecipientId !== null && $this->isDuplicate(
            $promoRecipientId,
            $page,
            $kind,
            $followKey,
            $visitedAt,
        )) {
            return null;
        }

        return PromoVisit::query()->create([
            'promo_recipient_id' => $promoRecipientId,
            'locale' => $locale,
            'page' => $page->value,
            'kind' => $kind->value,
            'follow_key' => $followKey?->value,
            'visited_at' => $visitedAt,
        ]);
    }

    private function isDuplicate(
        int $promoRecipientId,
        PromoVisitPage $page,
        PromoVisitKind $kind,
        ?PromoVisitFollowKey $followKey,
        CarbonInterface $visitedAt,
    ): bool {
        $query = PromoVisit::query()
            ->where('promo_recipient_id', $promoRecipientId)
            ->where('kind', $kind->value);

        if ($kind === PromoVisitKind::Follow) {
            return $query
                ->where('follow_key', $followKey?->value)
                ->exists();
        }

        return $query
            ->where('page', $page->value)
            ->where('visited_at', '>=', $visitedAt->copy()->subMinutes(self::RECIPIENT_DEDUPE_MINUTES))
            ->exists();
    }
}
