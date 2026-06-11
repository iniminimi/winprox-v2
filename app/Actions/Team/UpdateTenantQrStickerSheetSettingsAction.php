<?php

namespace App\Actions\Team;

use App\Data\Team\UpdateTenantQrStickerSheetSettingsData;
use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Audit\AuditRecorder;
use App\Support\Qr\BrandedQrStickerHeaderText;
use App\Support\TenantQrStickerBackgroundStorage;

class UpdateTenantQrStickerSheetSettingsAction
{
    public function __construct(
        private AuditRecorder $audit,
        private TenantQrStickerBackgroundStorage $backgroundStorage,
    ) {}

    public function handle(
        Tenant $tenant,
        UpdateTenantQrStickerSheetSettingsData $data,
        ?int $actorUserId = null,
    ): Tenant {
        $headerText = $data->headerText === null
            ? null
            : BrandedQrStickerHeaderText::fitForSticker($data->headerText);

        $data = new UpdateTenantQrStickerSheetSettingsData(
            template: $data->template,
            headerText: $headerText === '' ? null : $headerText,
            tenantLogoPlacement: $data->tenantLogoPlacement,
            showTenantAddress: $data->showTenantAddress,
        );

        $existing = TenantQrStickerSheetSetting::query()
            ->where('tenant_id', $tenant->id)
            ->where('template', $data->template->value)
            ->first();

        $backgroundPath = $existing?->background_path;

        if ($data->isEmpty($backgroundPath)) {
            if ($existing !== null) {
                $this->backgroundStorage->delete($existing->background_path);
                $settingId = (int) $existing->id;
                $existing->delete();

                $this->audit->record(
                    userId: $actorUserId,
                    tenantId: (int) $tenant->id,
                    action: 'tenant.qr_sticker_sheet_settings_cleared',
                    modelType: TenantQrStickerSheetSetting::class,
                    modelId: $settingId,
                    payload: [
                        'template' => $data->template->value,
                    ],
                );
            }

            return $tenant->fresh()->load('qrStickerSheetSettings');
        }

        $layoutConfig = $data->layoutConfig()->usesDefaults()
            ? null
            : $data->layoutConfig()->toArray();

        $setting = TenantQrStickerSheetSetting::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'template' => $data->template->value,
            ],
            [
                'header_text' => $data->headerText,
                'layout_config' => $layoutConfig,
            ],
        );

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.qr_sticker_sheet_settings_updated',
            modelType: TenantQrStickerSheetSetting::class,
            modelId: (int) $setting->id,
            payload: [
                'template' => $data->template->value,
                'header_text' => $setting->header_text,
                'background_path' => $setting->background_path,
                'layout_config' => $setting->layout_config,
            ],
        );

        return $tenant->fresh()->load('qrStickerSheetSettings');
    }
}
