<?php

declare(strict_types=1);

namespace App\Actions\Qr;

use App\Data\Qr\BrandedQrStickerPreviewData;
use App\Enums\QrStickerTenantLogoPlacement;
use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Qr\BrandedQrStickerHeaderText;
use App\Support\Qr\BrandedQrStickerCompositor;
use App\Support\Qr\BrandedQrStickerTenantDetails;
use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrStickerBackground;
use App\Support\Qr\QrStickerSheetTemplate;

class RenderBrandedQrStickerPreviewAction
{
    private const DEMO_REPORT_URL = 'https://example.test/melden/preview';

    private const DEMO_FOOTER_LABEL = 'Winprox-2606-00001';

    public function __construct(
        private readonly BrandedQrStickerCompositor $compositor = new BrandedQrStickerCompositor,
    ) {}

    public function handle(
        Tenant $tenant,
        BrandedQrStickerPreviewData $data,
        ?TenantQrStickerSheetSetting $sheetSetting = null,
    ): ?string {
        if (! QrCodePngWriter::canGenerate()) {
            return null;
        }

        $portalFallback = (string) __('settings.qr_stickers.avery_62x89_r.preview_portal_fallback');
        $sheetForHeader = $this->sheetSettingForHeaderPreview($data->headerText);

        $backgroundPath = QrStickerBackground::absolutePathForTemplate(
            QrStickerSheetTemplate::Avery62x89R,
            $sheetSetting,
        );

        $tenantStickerLogoPath = $data->tenantLogoPlacement !== QrStickerTenantLogoPlacement::None
            ? QrCenterLogo::tenantLogoAbsolutePath($tenant)
            : null;

        $tenantDetailLines = $data->showTenantAddress()
            ? BrandedQrStickerTenantDetails::lines($tenant)
            : [];

        $bytes = $this->compositor->compositeBytes(
            $backgroundPath,
            self::DEMO_REPORT_URL,
            QrCenterLogo::absolutePath($tenant),
            BrandedQrStickerHeaderText::resolve($sheetForHeader, $portalFallback),
            BrandedQrStickerHeaderText::unitCaption($sheetForHeader, $portalFallback),
            self::DEMO_FOOTER_LABEL,
            $tenantDetailLines !== [] ? $tenantDetailLines : null,
            $tenantStickerLogoPath,
            $data->tenantLogoPlacement,
        );

        return 'data:image/png;base64,'.base64_encode($bytes);
    }

    private function sheetSettingForHeaderPreview(?string $headerText): ?TenantQrStickerSheetSetting
    {
        if ($headerText === null || trim($headerText) === '') {
            return null;
        }

        $fitted = BrandedQrStickerHeaderText::fitForSticker($headerText);
        if ($fitted === '') {
            return null;
        }

        return new TenantQrStickerSheetSetting([
            'header_text' => $fitted,
        ]);
    }
}
