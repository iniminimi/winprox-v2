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
                $aPriority = $this->priority($summaries[(int) $a->id] ?? null);
                $bPriority = $this->priority($summaries[(int) $b->id] ?? null);

                if ($aPriority !== $bPriority) {
                    return $bPriority <=> $aPriority;
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
