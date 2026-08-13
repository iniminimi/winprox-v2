<?php

declare(strict_types=1);

namespace App\Data\Marketing;

final readonly class PromoCampaignVisitStatsData
{
    /**
     * @param  array<int, array{welcome: int, promo: int, engaged: int, follow: int, returning: bool}>  $byTargetId
     */
    public function __construct(
        public int $welcome,
        public int $promo,
        public int $engaged,
        public int $returning,
        public int $follow,
        public int $targetsWithVisits,
        public array $byTargetId,
    ) {}

    /**
     * @return array{welcome: int, promo: int, engaged: int, follow: int, returning: bool}
     */
    public function forTarget(int $targetId): array
    {
        return $this->byTargetId[$targetId] ?? [
            'welcome' => 0,
            'promo' => 0,
            'engaged' => 0,
            'follow' => 0,
            'returning' => false,
        ];
    }
}
