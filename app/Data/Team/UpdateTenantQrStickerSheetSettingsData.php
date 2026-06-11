<?php

namespace App\Data\Team;

use App\Support\Qr\QrStickerSheetTemplate;

readonly class UpdateTenantQrStickerSheetSettingsData
{
    public function __construct(
        public QrStickerSheetTemplate $template,
        public ?string $headerText,
    ) {}

    /** @param array{headerText?: ?string} $input */
    public static function fromValidated(QrStickerSheetTemplate $template, array $input): self
    {
        $headerText = isset($input['headerText']) ? trim((string) $input['headerText']) : null;

        return new self(
            template: $template,
            headerText: $headerText === '' ? null : $headerText,
        );
    }

    public function isEmpty(): bool
    {
        return ($this->headerText === null || $this->headerText === '');
    }
}
