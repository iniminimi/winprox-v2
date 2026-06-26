<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Models\PromoCampaign;

class GeneratePromoCampaignLettersAction
{
    public function __construct(
        private GeneratePromoCampaignLetterForTargetAction $generateLetter,
    ) {}

    /**
     * @return array{generated: int, skipped: int}
     */
    public function handle(
        PromoCampaign $campaign,
        int $actorUserId,
        string $promoBaseUrl,
        bool $overwriteExisting = false,
        ?int $limit = null,
    ): array {
        $query = $campaign->targets()->orderBy('id');
        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        $generated = 0;
        $skipped = 0;

        foreach ($query->get() as $target) {
            if ($target->generated_at !== null && ! $overwriteExisting) {
                $skipped++;

                continue;
            }

            $this->generateLetter->handle(
                campaign: $campaign,
                target: $target,
                actorUserId: $actorUserId,
                promoBaseUrl: $promoBaseUrl,
                overwriteExisting: $overwriteExisting,
            );
            $generated++;
        }

        return [
            'generated' => $generated,
            'skipped' => $skipped,
        ];
    }
}
