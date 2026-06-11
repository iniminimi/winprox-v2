<?php

namespace App\Actions\Team;

use App\Data\Team\UpdateTenantQrStickerSheetSettingsData;
use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Audit\AuditRecorder;
use App\Support\Qr\BrandedQrStickerLayoutConfig;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\TenantQrStickerBackgroundStorage;

class RemoveTenantQrStickerSheetBackgroundAction
{
    public function __construct(
        private TenantQrStickerBackgroundStorage $backgroundStorage,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        Tenant $tenant,
        QrStickerSheetTemplate $template,
        ?int $actorUserId = null,
    ): Tenant {
        $existing = TenantQrStickerSheetSetting::query()
            ->where('tenant_id', $tenant->id)
            ->where('template', $template->value)
            ->first();

        if ($existing === null || $existing->background_path === null) {
            return $tenant->fresh()->load('qrStickerSheetSettings');
        }

        $this->backgroundStorage->delete($existing->background_path);

        $layout = BrandedQrStickerLayoutConfig::fromSetting($existing);
        $data = new UpdateTenantQrStickerSheetSettingsData(
            template: $template,
            headerText: $existing->header_text,
            centerLogoMode: $layout->centerLogoMode(),
            cornerTenantLogo: $layout->showCornerTenantLogo(),
            showTenantAddress: $layout->showTenantAddress(),
        );

        if ($data->isEmpty(null)) {
            $settingId = (int) $existing->id;
            $existing->delete();

            $this->audit->record(
                userId: $actorUserId,
                tenantId: (int) $tenant->id,
                action: 'tenant.qr_sticker_sheet_background_removed',
                modelType: TenantQrStickerSheetSetting::class,
                modelId: $settingId,
                payload: [
                    'template' => $template->value,
                    'row_deleted' => true,
                ],
            );

            return $tenant->fresh()->load('qrStickerSheetSettings');
        }

        $existing->update(['background_path' => null]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.qr_sticker_sheet_background_removed',
            modelType: TenantQrStickerSheetSetting::class,
            modelId: (int) $existing->id,
            payload: [
                'template' => $template->value,
                'row_deleted' => false,
            ],
        );

        return $tenant->fresh()->load('qrStickerSheetSettings');
    }
}
