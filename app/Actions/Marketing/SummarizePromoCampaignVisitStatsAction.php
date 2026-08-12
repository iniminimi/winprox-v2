<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Data\Marketing\PromoCampaignVisitStatsData;
use App\Enums\PromoVisitPage;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignTarget;
use App\Models\PromoVisit;

class SummarizePromoCampaignVisitStatsAction
{
    public function handle(PromoCampaign $campaign): PromoCampaignVisitStatsData
    {
        $targets = PromoCampaignTarget::query()
            ->where('promo_campaign_id', $campaign->id)
            ->get(['id', 'promo_recipient_id']);

        /** @var array<int, int> $recipientIdByTargetId */
        $recipientIdByTargetId = [];
        foreach ($targets as $target) {
            if ($target->promo_recipient_id !== null) {
                $recipientIdByTargetId[(int) $target->id] = (int) $target->promo_recipient_id;
            }
        }

        $recipientIds = array_values(array_unique(array_values($recipientIdByTargetId)));

        if ($recipientIds === []) {
            $byTargetId = [];
            foreach ($targets as $target) {
                $byTargetId[(int) $target->id] = ['welcome' => 0, 'promo' => 0];
            }

            return new PromoCampaignVisitStatsData(
                welcome: 0,
                promo: 0,
                targetsWithVisits: 0,
                byTargetId: $byTargetId,
            );
        }

        /** @var array<int, array{welcome: int, promo: int}> $countsByRecipientId */
        $countsByRecipientId = [];
        foreach ($recipientIds as $recipientId) {
            $countsByRecipientId[$recipientId] = ['welcome' => 0, 'promo' => 0];
        }

        $visitRows = PromoVisit::query()
            ->whereIn('promo_recipient_id', $recipientIds)
            ->selectRaw('promo_recipient_id, page, COUNT(*) as aggregate')
            ->groupBy('promo_recipient_id', 'page')
            ->get();

        $welcomeTotal = 0;
        $promoTotal = 0;

        foreach ($visitRows as $row) {
            $recipientId = (int) $row->promo_recipient_id;
            $count = (int) $row->aggregate;
            $page = $row->page instanceof PromoVisitPage
                ? $row->page
                : PromoVisitPage::tryFrom((string) $row->page);

            if ($page === PromoVisitPage::Welcome) {
                $countsByRecipientId[$recipientId]['welcome'] = $count;
                $welcomeTotal += $count;
            } elseif ($page === PromoVisitPage::Promo) {
                $countsByRecipientId[$recipientId]['promo'] = $count;
                $promoTotal += $count;
            }
        }

        $targetsWithVisits = 0;
        /** @var array<int, array{welcome: int, promo: int}> $byTargetId */
        $byTargetId = [];

        foreach ($targets as $target) {
            $targetId = (int) $target->id;
            $recipientId = $recipientIdByTargetId[$targetId] ?? null;
            $counts = $recipientId !== null
                ? ($countsByRecipientId[$recipientId] ?? ['welcome' => 0, 'promo' => 0])
                : ['welcome' => 0, 'promo' => 0];

            $byTargetId[$targetId] = $counts;

            if ($counts['welcome'] > 0 || $counts['promo'] > 0) {
                $targetsWithVisits++;
            }
        }

        return new PromoCampaignVisitStatsData(
            welcome: $welcomeTotal,
            promo: $promoTotal,
            targetsWithVisits: $targetsWithVisits,
            byTargetId: $byTargetId,
        );
    }
}
