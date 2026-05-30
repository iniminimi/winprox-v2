<?php

declare(strict_types=1);

namespace App\Support\Qr;

final readonly class QrStickerEntry
{
    public function __construct(
        public string $primaryLabel,
        public string $secondaryLabel,
        public string $reportUrl,
    ) {}
}
