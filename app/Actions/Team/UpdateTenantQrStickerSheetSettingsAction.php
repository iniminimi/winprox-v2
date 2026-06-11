<?php

namespace App\Actions\Team;

use App\Actions\Team\Concerns\ResolvesTenantQrStickerSheetSetting;
use App\Data\Team\UpdateTenantQrStickerSheetSettingsData;
use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Audit\AuditRecorder;
use App\Support\Qr\BrandedQrStickerHeaderText;
use App\Support\TenantQrStickerBackgroundStorage;

class UpdateTenantQrStickerSheetSettingsAction
{
    use ResolvesTenantQrStickerSheetSetting;

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
            tenantAddressPlacement: $data->tenantAddressPlacement,
        );

        $existing = $this->findTenantQrStickerSheetSetting((int) $tenant->id, $data->template);

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

        $attributes = [
            'header_text' => $data->headerText,
            'layout_config' => $layoutConfig,
        ];

        if ($existing?->background_path !== null && $existing->background_path !== '') {
            $attributes['background_path'] = $existing->background_path;
        }

        $setting = TenantQrStickerSheetSetting::query()
            ->withoutGlobalScope('tenant')
            ->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'template' => $data->template->value,
                ],
                $attributes,
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
