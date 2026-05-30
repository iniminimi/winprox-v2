<?php

declare(strict_types=1);

namespace App\Http\Controllers\Locations;

use App\Models\Location;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\Qr\Word\QrStickerWordExporter;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class LocationQrPackDownloadController
{
    public function __invoke(Location $location, QrStickerWordExporter $exporter): Response|SymfonyResponse
    {
        $this->authorize($location);

        if (! QrCodePngWriter::canGenerate()) {
            abort(503, __('locations.qr_pack.unavailable'));
        }

        try {
            $template = QrStickerSheetTemplate::Avery55x55S;
            $binary = $exporter->buildDocxBinary($location, $template);
            $filename = $exporter->downloadFilename($location, $template);
        } catch (InvalidArgumentException $exception) {
            abort(503, $exception->getMessage());
        }

        return response()->streamDownload(
            static function () use ($binary): void {
                echo $binary;
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ],
        );
    }

    private function authorize(Location $location): void
    {
        abort_unless(auth()->check(), 403);
        abort_unless(
            SuperuserTenantAccess::canAccessTenant(auth()->user(), (int) $location->tenant_id),
            403,
        );
    }
}
