<?php

declare(strict_types=1);

namespace App\Support\Qr\Word;

use App\Models\Location;
use App\Support\Qr\LocationQrPackStickerEntries;
use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrStickerSheetTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class QrStickerWordExporter
{
    public function __construct(
        private readonly Avery55x55WordStickerSheetBuilder $avery55x55Builder = new Avery55x55WordStickerSheetBuilder,
    ) {}

    public function downloadFilename(Location $location, QrStickerSheetTemplate $template): string
    {
        $siteSlug = Str::slug($location->name) ?: 'location';

        return sprintf(
            'winprox-qr-%s-%s-%s.docx',
            $siteSlug,
            $template->averySku(),
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

        $location->loadMissing('tenant');
        $entries = LocationQrPackStickerEntries::forLocation($location);
        $headline = __('locations.qr_pack.headline');
        $centerLogoPath = QrCenterLogo::absolutePath($location->tenant);

        return match ($template) {
            QrStickerSheetTemplate::Avery55x55S => $this->avery55x55Builder->build($entries, $headline, $centerLogoPath),
        };
    }
}
