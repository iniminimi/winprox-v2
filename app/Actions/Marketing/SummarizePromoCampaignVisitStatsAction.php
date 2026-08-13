<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Data\Marketing\PromoCampaignVisitStatsData;
use App\Enums\PromoVisitKind;
use App\Enums\PromoVisitPage;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignTarget;
use App\Models\PromoVisit;
use Illuminate\Support\Carbon;

class SummarizePromoCampaignVisitStatsAction
{
    private const RETURN_GAP_MINUTES = 30;

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

        $emptyTargetCounts = [
            'welcome' => 0,
            'promo' => 0,
            'engaged' => 0,
            'follow' => 0,
            'returning' => false,
        ];

        $recipientIds = array_values(array_unique(array_values($recipientIdByTargetId)));

        if ($recipientIds === []) {
            $byTargetId = [];
            foreach ($targets as $target) {
                $byTargetId[(int) $target->id] = $emptyTargetCounts;
            }

            return new PromoCampaignVisitStatsData(
                welcome: 0,
                promo: 0,
                engaged: 0,
                returning: 0,
                follow: 0,
                targetsWithVisits: 0,
                byTargetId: $byTargetId,
            );
        }

        /** @var array<int, array{welcome: int, promo: int, engaged: int, follow: int, returning: bool}> $countsByRecipientId */
        $countsByRecipientId = [];
        foreach ($recipientIds as $recipientId) {
            $countsByRecipientId[$recipientId] = $emptyTargetCounts;
        }

        $visitRows = PromoVisit::query()
            ->whereIn('promo_recipient_id', $recipientIds)
            ->selectRaw('promo_recipient_id, page, kind, COUNT(*) as aggregate')
            ->groupBy('promo_recipient_id', 'page', 'kind')
            ->get();

        $welcomeTotal = 0;
        $promoTotal = 0;
        $engagedTotal = 0;
        $followTotal = 0;

        foreach ($visitRows as $row) {
            $recipientId = (int) $row->promo_recipient_id;
            $count = (int) $row->aggregate;
            $kind = $row->kind instanceof PromoVisitKind
                ? $row->kind
                : PromoVisitKind::tryFrom((string) $row->kind);
            $page = $row->page instanceof PromoVisitPage
                ? $row->page
                : PromoVisitPage::tryFrom((string) $row->page);

            if ($kind === PromoVisitKind::Engaged) {
                $countsByRecipientId[$recipientId]['engaged'] += $count;
                $engagedTotal += $count;

                continue;
            }

            if ($kind === PromoVisitKind::Follow) {
                $countsByRecipientId[$recipientId]['follow'] += $count;
                $followTotal += $count;

                continue;
            }

            if ($kind !== PromoVisitKind::Hit && $kind !== null) {
                continue;
            }

            if ($page === PromoVisitPage::Welcome) {
                $countsByRecipientId[$recipientId]['welcome'] += $count;
                $welcomeTotal += $count;
            } elseif ($page === PromoVisitPage::Promo) {
                $countsByRecipientId[$recipientId]['promo'] += $count;
                $promoTotal += $count;
            }
        }

        $engagedSpans = PromoVisit::query()
            ->whereIn('promo_recipient_id', $recipientIds)
            ->where('kind', PromoVisitKind::Engaged->value)
            ->selectRaw('promo_recipient_id, MIN(visited_at) as first_at, MAX(visited_at) as last_at, COUNT(*) as aggregate')
            ->groupBy('promo_recipient_id')
            ->get();

        $returningRecipientIds = [];
        foreach ($engagedSpans as $span) {
            if ((int) $span->aggregate < 2) {
                continue;
            }

            $firstAt = Carbon::parse($span->first_at);
            $lastAt = Carbon::parse($span->last_at);
            if ($firstAt->diffInMinutes($lastAt) < self::RETURN_GAP_MINUTES) {
                continue;
            }

            $returningRecipientIds[(int) $span->promo_recipient_id] = true;
        }

        $targetsWithVisits = 0;
        $returning = 0;
        /** @var array<int, array{welcome: int, promo: int, engaged: int, follow: int, returning: bool}> $byTargetId */
        $byTargetId = [];

        foreach ($targets as $target) {
            $targetId = (int) $target->id;
            $recipientId = $recipientIdByTargetId[$targetId] ?? null;
            $counts = $recipientId !== null
                ? ($countsByRecipientId[$recipientId] ?? $emptyTargetCounts)
                : $emptyTargetCounts;

            $isReturning = $recipientId !== null && isset($returningRecipientIds[$recipientId]);
            $counts['returning'] = $isReturning;
            $byTargetId[$targetId] = $counts;

            if ($counts['welcome'] > 0 || $counts['promo'] > 0 || $counts['engaged'] > 0 || $counts['follow'] > 0) {
                $targetsWithVisits++;
            }

            if ($isReturning) {
                $returning++;
            }
        }

        return new PromoCampaignVisitStatsData(
            welcome: $welcomeTotal,
            promo: $promoTotal,
            engaged: $engagedTotal,
            returning: $returning,
            follow: $followTotal,
            targetsWithVisits: $targetsWithVisits,
            byTargetId: $byTargetId,
        );
    }
}
