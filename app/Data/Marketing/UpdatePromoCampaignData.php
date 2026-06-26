<?php

declare(strict_types=1);

namespace App\Data\Marketing;

final class UpdatePromoCampaignData
{
    /**
     * @param  array<string, string>|null  $columnMapping
     */
    public function __construct(
        public readonly string $name,
        public readonly string $locale,
        public readonly ?string $letterBodyHtml,
        public readonly ?string $emailSubject,
        public readonly ?string $emailBodyHtml,
        public readonly ?string $flowImagePath,
        public readonly ?array $columnMapping,
    ) {}
}
