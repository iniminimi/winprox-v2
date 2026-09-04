<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Data\Marketing\PromoCampaignDeliverySummaryData;
use App\Enums\PromoCampaignDeliveryStatus;
use App\Models\PromoCampaign;
use Illuminate\Support\Collection;

class SortPromoCampaignsForPlatformListAction
{
    /**
     * @param  Collection<int, PromoCampaign>  $campaigns
     * @param  array<int, PromoCampaignDeliverySummaryData>  $summaries
     * @return Collection<int, PromoCampaign>
     */
    public function handle(Collection $campaigns, array $summaries): Collection
    {
        return $campaigns
            ->sort(function (PromoCampaign $a, PromoCampaign $b) use ($summaries): int {
                $aSummary = $summaries[(int) $a->id] ?? null;
                $bSummary = $summaries[(int) $b->id] ?? null;

                $aPriority = $this->priority($aSummary);
                $bPriority = $this->priority($bSummary);

                if ($aPriority !== $bPriority) {
                    return $bPriority <=> $aPriority;
                }

                $aLastSent = $aSummary?->lastSentAt?->getTimestamp() ?? 0;
                $bLastSent = $bSummary?->lastSentAt?->getTimestamp() ?? 0;

                if ($aLastSent !== $bLastSent) {
                    return $bLastSent <=> $aLastSent;
                }

                return $b->id <=> $a->id;
            })
            ->values();
    }

    private function priority(?PromoCampaignDeliverySummaryData $summary): int
    {
        return $summary?->status === PromoCampaignDeliveryStatus::Sending ? 1 : 0;
    }
}
