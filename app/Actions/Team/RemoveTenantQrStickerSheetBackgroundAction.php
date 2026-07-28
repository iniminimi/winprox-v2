<?php

namespace App\Actions\Team;

use App\Actions\Team\Concerns\ResolvesTenantQrStickerSheetSetting;
use App\Data\Team\UpdateTenantQrStickerSheetSettingsData;
use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Audit\AuditRecorder;
use App\Support\Qr\BrandedQrStickerLayoutConfig;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\TenantQrStickerBackgroundStorage;

class RemoveTenantQrStickerSheetBackgroundAction
{
    use ResolvesTenantQrStickerSheetSetting;

    public function __construct(
        private TenantQrStickerBackgroundStorage $backgroundStorage,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        Tenant $tenant,
        QrStickerSheetTemplate $template,
        ?int $actorUserId = null,
    ): Tenant {
        $existing = $this->findTenantQrStickerSheetSetting((int) $tenant->id, $template);

        if ($existing === null || $existing->background_path === null) {
            return $tenant->fresh()->load('qrStickerSheetSettings');
        }

        $this->backgroundStorage->delete($existing->background_path);

        if ($template === QrStickerSheetTemplate::PrintablePage) {
            $preset = \App\Enums\QrPrintablePageBackgroundPreset::fromSetting($existing);
            $branding = BrandedQrStickerLayoutConfig::fromSetting($existing);
            $isDefaultOnly = $preset === \App\Enums\QrPrintablePageBackgroundPreset::default()
                && $branding->usesDefaults();

            if ($isDefaultOnly) {
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

        $layout = BrandedQrStickerLayoutConfig::fromSetting($existing);
        $data = new UpdateTenantQrStickerSheetSettingsData(
            template: $template,
            headerText: $existing->header_text,
            tenantLogoPlacement: $layout->tenantLogoPlacement(),
            tenantAddressPlacement: $layout->tenantAddressPlacement(),
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
