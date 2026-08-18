<?php

declare(strict_types=1);

namespace App\Data\Onboarding;

use App\Enums\TenantStarterPackType;
use App\Support\Translation\LocaleSupport;

readonly class ApplyTenantStarterPackData
{
    public function __construct(
        public TenantStarterPackType $type,
        public string $locale,
    ) {}

    /**
     * @param  array{starterPackType?: string, locale?: string|null}  $input
     */
    public static function fromValidated(array $input, ?string $fallbackLocale = null): self
    {
        return new self(
            type: TenantStarterPackType::from((string) $input['starterPackType']),
            locale: LocaleSupport::normalize($input['locale'] ?? $fallbackLocale),
        );
    }
}
