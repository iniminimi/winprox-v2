<?php

declare(strict_types=1);

namespace App\Actions\Qr;

use App\Data\Qr\BrandedQrPrintablePagePreviewData;
use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrPrintablePageBackground;
use App\Support\Qr\QrPrintablePagePreviewComposer;
use App\Support\Qr\QrStickerSheetTemplate;

class RenderQrPrintablePagePreviewAction
{
    public function __construct(
        private readonly QrPrintablePagePreviewComposer $composer = new QrPrintablePagePreviewComposer,
    ) {}

    public function handle(
        Tenant $tenant,
        BrandedQrPrintablePagePreviewData $data,
        ?TenantQrStickerSheetSetting $sheetSetting = null,
    ): ?string {
        if (! QrCodePngWriter::canGenerate()) {
            return null;
        }

        $previewSetting = new TenantQrStickerSheetSetting([
            'background_path' => $sheetSetting?->background_path,
            'layout_config' => $data->layoutConfigForPreview(),
        ]);

        try {
            $backgroundPath = QrPrintablePageBackground::absolutePathForTemplate(
                QrStickerSheetTemplate::A6Print,
                $previewSetting,
            );

            $bytes = $this->composer->composePngBytes(
                $backgroundPath,
                $tenant,
                $data->brandingLayout(),
            );
        } catch (\Throwable) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($bytes);
    }
}
