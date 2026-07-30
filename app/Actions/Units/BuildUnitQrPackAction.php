<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Actions\QrCodes\EnsureUnitStickerQrCodeAction;
use App\Data\Units\UnitQrPackResult;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Qr\LocationQrPackStickerEntries;
use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\Qr\Word\QrStickerWordExporter;
use InvalidArgumentException;

final class BuildUnitQrPackAction
{
    public function __construct(
        private readonly QrStickerWordExporter $exporter,
        private readonly EnsureUnitQrTokenAction $ensureUnitQrToken,
        private readonly EnsureUnitStickerQrCodeAction $ensureUnitStickerQrCode,
    ) {}

    public function handle(
        Unit $unit,
        QrStickerSheetTemplate $template,
        int $tenantId,
        ?int $actorUserId = null,
    ): UnitQrPackResult {
        if (! $template->isPrintablePage()) {
            throw new InvalidArgumentException('Unit QR pack only supports A6/A5/A4 printable templates.');
        }

        if ((int) $unit->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Unit does not belong to the given tenant.');
        }

        if (! QrCodePngWriter::canGenerate()) {
            throw new InvalidArgumentException(
                'QR sticker export is unavailable: enable the PHP gd or imagick extension on the server.',
            );
        }

        $this->ensureUnitQrToken->handle($unit);
        $unit->loadMissing(['location', 'qrCodes']);

        $entries = LocationQrPackStickerEntries::forUnit(
            $unit,
            $this->ensureUnitStickerQrCode,
            $actorUserId,
        );

        if ($entries === []) {
            throw new InvalidArgumentException('Unit has no location or QR token for sticker export.');
        }

        $tenant = Tenant::query()
            ->with('qrStickerSheetSettings')
            ->findOrFail($tenantId);

        $binary = $this->exporter->buildDocxBinaryFromEntries(
            $entries,
            $template,
            QrCenterLogo::absolutePath($tenant),
            $tenant,
        );

        return new UnitQrPackResult(
            binary: $binary,
            filename: $this->exporter->downloadFilenameForUnit($template),
        );
    }
}
