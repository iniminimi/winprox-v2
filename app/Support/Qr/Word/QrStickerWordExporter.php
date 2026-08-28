<?php

declare(strict_types=1);

namespace App\Support\Qr\Word;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Qr\LocationQrPackStickerEntries;
use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrStickerEntry;
use App\Support\Qr\QrStickerRasterCache;
use App\Support\Qr\QrStickerSheetTemplate;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class QrStickerWordExporter
{
    public function __construct(
        private readonly Avery55x55WordStickerSheetBuilder $avery55x55Builder = new Avery55x55WordStickerSheetBuilder,
        private readonly Herma7050WordStickerSheetBuilder $herma7050Builder = new Herma7050WordStickerSheetBuilder,
        private readonly Avery62x89WordStickerSheetBuilder $avery62x89Builder = new Avery62x89WordStickerSheetBuilder,
        private readonly QrPrintablePageWordBuilder $printablePageBuilder = new QrPrintablePageWordBuilder,
    ) {}

    public function downloadFilename(Location $location, QrStickerSheetTemplate $template): string
    {
        return sprintf(
            'winprox-qr-%s-%s.docx',
            $template->fileSlug(),
            Carbon::now()->timezone(config('app.timezone'))->format('Y-m-d-His'),
        );
    }

    public function downloadFilenameForClockPoint(QrStickerSheetTemplate $template): string
    {
        return sprintf(
            'winprox-qr-clock-%s-%s.docx',
            $template->fileSlug(),
            Carbon::now()->timezone(config('app.timezone'))->format('Y-m-d-His'),
        );
    }

    public function downloadFilenameForUnit(QrStickerSheetTemplate $template): string
    {
        return sprintf(
            'winprox-qr-unit-%s-%s.docx',
            $template->fileSlug(),
            Carbon::now()->timezone(config('app.timezone'))->format('Y-m-d-His'),
        );
    }

    public function buildDocxBinary(Location $location, QrStickerSheetTemplate $template): string
    {
        if (! QrCodePngWriter::canGenerate()) {
            throw new InvalidArgumentException(
                'QR sticker export is unavailable: enable the PHP gd or imagick extension on the server.',
            );
        }

        $location->loadMissing(['tenant.qrStickerSheetSettings']);
        $entries = LocationQrPackStickerEntries::forLocation($location);
        $centerLogoPath = QrCenterLogo::absolutePath($location->tenant);

        return $this->buildDocxBinaryFromEntries($entries, $template, $centerLogoPath, $location->tenant);
    }

    /**
     * @param  list<QrStickerEntry>  $entries
     */
    public function buildDocxBinaryFromEntries(
        array $entries,
        QrStickerSheetTemplate $template,
        ?string $centerLogoPath = null,
        ?Tenant $tenant = null,
    ): string
    {
        if (! QrCodePngWriter::canGenerate()) {
            throw new InvalidArgumentException(
                'QR sticker export is unavailable: enable the PHP gd or imagick extension on the server.',
            );
        }

        if ($centerLogoPath === null) {
            $centerLogoPath = QrCenterLogo::absolutePath($tenant);
        }

        $tenant?->loadMissing('qrStickerSheetSettings');
        $sheetSettings = $template->isPrintablePage()
            ? $this->printableSheetSettingsForExport($tenant)
            : $tenant?->qrStickerSheetSetting($template);

        try {
            return match ($template) {
                QrStickerSheetTemplate::Avery55x55S => $this->avery55x55Builder->build($entries, $centerLogoPath),
                QrStickerSheetTemplate::Herma7050 => $this->herma7050Builder->build($entries, $centerLogoPath),
                QrStickerSheetTemplate::Avery62x89R => $this->avery62x89Builder->build($entries, $centerLogoPath, $tenant, $sheetSettings),
                QrStickerSheetTemplate::A6Print,
                QrStickerSheetTemplate::A5Print,
                QrStickerSheetTemplate::A4Print => $this->printablePageBuilder->build(
                    $entries,
                    $template,
                    $centerLogoPath,
                    $tenant,
                    $sheetSettings,
                ),
            };
        } finally {
            QrStickerRasterCache::clear();
        }
    }

    private function printableSheetSettingsForExport(?Tenant $tenant): ?TenantQrStickerSheetSetting
    {
        if ($tenant === null) {
            return null;
        }

        $printable = $tenant->qrStickerSheetSetting(QrStickerSheetTemplate::printablePageSettings());
        $avery = $tenant->qrStickerSheetSetting(QrStickerSheetTemplate::Avery62x89R);
        $headerText = $avery?->header_text ?? $printable?->header_text;

        if ($printable === null && $avery === null) {
            return null;
        }

        if ($printable !== null) {
            if ($headerText !== null && $printable->header_text !== $headerText) {
                return new TenantQrStickerSheetSetting([
                    'tenant_id' => $printable->tenant_id,
                    'template' => $printable->template,
                    'header_text' => $headerText,
                    'background_path' => $printable->background_path,
                    'layout_config' => $printable->layout_config,
                ]);
            }

            return $printable;
        }

        return $avery;
    }
}
