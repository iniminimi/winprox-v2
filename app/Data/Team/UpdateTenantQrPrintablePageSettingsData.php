<?php

declare(strict_types=1);

namespace App\Data\Team;

use App\Enums\QrPrintablePageBackgroundPreset;

readonly class UpdateTenantQrPrintablePageSettingsData
{
    public function __construct(
        public QrPrintablePageBackgroundPreset $preset,
    ) {}

    /**
     * @param  array{preset?: string}  $input
     */
    public static function fromValidated(array $input): self
    {
        return new self(
            preset: QrPrintablePageBackgroundPreset::tryFrom((string) ($input['preset'] ?? ''))
                ?? QrPrintablePageBackgroundPreset::default(),
        );
    }

    /**
     * @return array{background_preset: string}
     */
    public function layoutConfig(): array
    {
        return [
            QrPrintablePageBackgroundPreset::LAYOUT_KEY => $this->preset->value,
        ];
    }

    public function isDefaultPreset(): bool
    {
        return $this->preset === QrPrintablePageBackgroundPreset::default();
    }
}
