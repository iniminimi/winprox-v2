<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Enums\PromoBounceKind;
use App\Support\Marketing\PromoBounceMessageParser;

class HaltPromoSendingOnListedDomainAction
{
    public function __construct(
        private PausePromoCampaignSendingAction $pauseSending,
    ) {}

    /**
     * Pause all promo sending when a bounce shows our domain/IP is listed (Spamhaus DBL, etc.).
     */
    public function handle(string $haystack, bool $dryRun = false, ?int $actorUserId = null): bool
    {
        if ($dryRun || PromoBounceMessageParser::classify($haystack) !== PromoBounceKind::DomainBlock) {
            return false;
        }

        $this->pauseSending->handle(null, $actorUserId, 'domain_block');

        return true;
    }
}
