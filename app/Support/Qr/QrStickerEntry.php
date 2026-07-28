<?php

declare(strict_types=1);

namespace App\Support\Qr;

final readonly class QrStickerEntry
{
    public function __construct(
        /** Label below QR on plain sticker sheets (unit name or sticker number). */
        public string $unitLabel,
        public string $reportUrl,
        /** Portal-style header when tenant Avery 62×89 text is empty (location · unit). */
        public ?string $headerFallback = null,
        /** Winprox sticker number below QR on branded Avery 62×89 sheets. */
        public ?string $stickerNumber = null,
        /** Second line on printable A6/A5/A4 pages: "location - unit". */
        public ?string $locationUnitLabel = null,
    ) {}
}
