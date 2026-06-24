<?php

declare(strict_types=1);

namespace App\Data\Marketing;

final class MunicipalPromoEmailCandidateData
{
    public function __construct(
        public MunicipalPromoLetterData $municipality,
        public ?int $promoRecipientId,
        public string $promoToken,
        public string $promoUrl,
        public string $docxPath,
        public string $recipientEmail,
        public ?string $blockReason = null,
    ) {}

    public function isReady(): bool
    {
        return $this->blockReason === null;
    }
}
