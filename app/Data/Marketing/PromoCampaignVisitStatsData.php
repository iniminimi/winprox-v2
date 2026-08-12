<?php

declare(strict_types=1);

namespace App\Data\Marketing;

final readonly class PromoCampaignVisitStatsData
{
    /**
     * @param  array<int, array{welcome: int, promo: int}>  $byTargetId
     */
    public function __construct(
        public int $welcome,
        public int $promo,
        public int $targetsWithVisits,
        public array $byTargetId,
    ) {}

    /**
     * @return array{welcome: int, promo: int}
     */
    public function forTarget(int $targetId): array
    {
        return $this->byTargetId[$targetId] ?? ['welcome' => 0, 'promo' => 0];
    }
}
