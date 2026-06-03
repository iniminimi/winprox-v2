<?php

declare(strict_types=1);

namespace App\Http\Controllers\Locations;

use App\Actions\QrCodes\BatchGenerateQrCodesAction;
use App\Models\Location;
use App\Models\QrCode;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Qr\LocationQrPackStickerEntries;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrStickerEntry;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\Qr\UnitPortalUrl;
use App\Support\Qr\Word\QrStickerWordExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class LocationQrPackDownloadController
{
    public function __invoke(Location $location, Request $request, QrStickerWordExporter $exporter, BatchGenerateQrCodesAction $batchGenerate): Response|SymfonyResponse
    {
        $this->authorize($location);

        if (! QrCodePngWriter::canGenerate()) {
            abort(503, __('locations.qr_pack.unavailable'));
        }

        $isDynamic = $request->query('dynamic') === '1';
        $dynamicCount = (int) ($request->query('count') ?? 15);

        if ($isDynamic) {
            // Validate count
            if ($dynamicCount < 1 || $dynamicCount > 100) {
                abort(400, __('locations.qr_pack.invalid_count'));
            }

            // Generate dynamic QR codes
            $qrCodes = $batchGenerate->handle($dynamicCount, (int) $location->tenant_id, (int) auth()->id());

            // Build sticker entries from dynamic QR codes
            $entries = [];
            foreach ($qrCodes as $qrCode) {
                $entries[] = new QrStickerEntry(
                    $qrCode->sticker_number,
                    route('qr.scan', ['token' => $qrCode->token]),
                );
            }
        } else {
            // Use existing location units
            $entries = LocationQrPackStickerEntries::forLocation($location);

            if ($entries === []) {
                abort(404, __('locations.qr_pack.no_units'));
            }
        }

        $template = QrStickerSheetTemplate::tryFrom((string) $request->query('template', ''))
            ?? QrStickerSheetTemplate::Avery55x55S;

        try {
            $binary = $exporter->buildDocxBinaryFromEntries($entries, $template);
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
