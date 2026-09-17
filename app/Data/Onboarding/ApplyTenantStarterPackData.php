<?php

declare(strict_types=1);

namespace App\Data\Onboarding;

use App\Enums\TenantStarterPackSize;
use App\Enums\TenantStarterPackType;
use App\Support\Translation\LocaleSupport;

readonly class ApplyTenantStarterPackData
{
    public function __construct(
        public TenantStarterPackType $type,
        public string $locale,
        public ?TenantStarterPackSize $size = null,
    ) {}

    /**
     * @param  array{starterPackType?: string, starterPackSize?: string|null, locale?: string|null}  $input
     */
    public static function fromValidated(array $input, ?string $fallbackLocale = null): self
    {
        $type = TenantStarterPackType::from((string) $input['starterPackType']);
        $size = TenantStarterPackSize::tryFrom((string) ($input['starterPackSize'] ?? ''));

        if ($type->asksCompanySize()) {
            $size ??= TenantStarterPackSize::Large;
        } else {
            $size = null;
        }

        return new self(
            type: $type,
            locale: LocaleSupport::normalize($input['locale'] ?? $fallbackLocale),
            size: $size,
        );
    }
}
