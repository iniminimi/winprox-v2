<?php

declare(strict_types=1);

namespace App\Actions\Time;

use App\Data\Time\ClockPointQrPackResult;
use App\Models\ClockPoint;
use App\Models\Tenant;
use App\Support\Qr\ClockPointQrPackStickerEntries;
use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\Qr\Word\QrStickerWordExporter;
use InvalidArgumentException;

final class BuildClockPointQrPackAction
{
    public function __construct(
        private readonly QrStickerWordExporter $exporter,
    ) {}

    public function handle(
        ClockPoint $clockPoint,
        QrStickerSheetTemplate $template,
        int $tenantId,
        ?int $actorUserId = null,
    ): ClockPointQrPackResult {
        if (! $template->isPrintablePage()) {
            throw new InvalidArgumentException('Clock-point QR pack only supports A6/A5/A4 printable templates.');
        }

        if ((int) $clockPoint->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Clock point does not belong to the given tenant.');
        }

        if (! QrCodePngWriter::canGenerate()) {
            throw new InvalidArgumentException(
                'QR sticker export is unavailable: enable the PHP gd or imagick extension on the server.',
            );
        }

        $clockPoint->loadMissing('location');

        $tenant = Tenant::query()
            ->with('qrStickerSheetSettings')
            ->findOrFail($tenantId);

        $binary = $this->exporter->buildDocxBinaryFromEntries(
            ClockPointQrPackStickerEntries::forClockPoint($clockPoint),
            $template,
            QrCenterLogo::absolutePath($tenant),
            $tenant,
        );

        return new ClockPointQrPackResult(
            binary: $binary,
            filename: $this->exporter->downloadFilenameForClockPoint($template),
        );
    }
}
