<?php

declare(strict_types=1);

namespace App\Http\Controllers\Time;

use App\Models\ClockPoint;
use App\Models\Tenant;
use App\Support\Qr\ClockPointQrPackStickerEntries;
use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\Qr\Word\QrStickerWordExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class ClockPointQrPackDownloadController
{
    public function __invoke(
        ClockPoint $clockPoint,
        Request $request,
        QrStickerWordExporter $exporter,
    ): Response|SymfonyResponse {
        Gate::authorize('view', $clockPoint);

        if (! QrCodePngWriter::canGenerate()) {
            abort(503, __('time.clock_points.qr.pack.unavailable'));
        }

        @set_time_limit(120);

        $template = QrStickerSheetTemplate::tryFrom((string) $request->query('template', ''));
        if ($template === null || ! $template->isPrintablePage()) {
            abort(404);
        }

        $clockPoint->loadMissing('location');
        $entries = ClockPointQrPackStickerEntries::forClockPoint($clockPoint);

        $tenant = Tenant::query()
            ->with('qrStickerSheetSettings')
            ->findOrFail($clockPoint->tenant_id);
        $centerLogoPath = QrCenterLogo::absolutePath($tenant);

        try {
            $binary = $exporter->buildDocxBinaryFromEntries($entries, $template, $centerLogoPath, $tenant);
            $filename = $exporter->downloadFilenameForClockPoint($template);
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
}
