<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Actions\Team\Concerns\ResolvesTenantQrStickerSheetSetting;
use App\Data\Team\UpdateTenantQrPrintablePageSettingsData;
use App\Enums\QrPrintablePageBackgroundPreset;
use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Audit\AuditRecorder;
use App\Support\Qr\BrandedQrStickerHeaderText;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\TenantQrStickerBackgroundStorage;

class UpdateTenantQrPrintablePageSettingsAction
{
    use ResolvesTenantQrStickerSheetSetting;

    public function __construct(
        private AuditRecorder $audit,
        private TenantQrStickerBackgroundStorage $backgroundStorage,
    ) {}

    public function handle(
        Tenant $tenant,
        UpdateTenantQrPrintablePageSettingsData $data,
        ?int $actorUserId = null,
    ): Tenant {
        $template = QrStickerSheetTemplate::printablePageSettings();
        $existing = $this->findTenantQrStickerSheetSetting((int) $tenant->id, $template);
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
                        'template' => $template->value,
                    ],
                );
            }

            return $tenant->fresh()->load('qrStickerSheetSettings');
        }

        $headerText = $data->headerText === null
            ? null
            : BrandedQrStickerHeaderText::fitForSticker($data->headerText);
        $headerText = $headerText === '' ? null : $headerText;

        $attributes = [
            'header_text' => $headerText,
            'layout_config' => $data->layoutConfig(),
        ];

        if (is_string($backgroundPath) && $backgroundPath !== '') {
            $attributes['background_path'] = $backgroundPath;
        }

        $setting = TenantQrStickerSheetSetting::query()
            ->withoutGlobalScope('tenant')
            ->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'template' => $template->value,
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
                'template' => $template->value,
                'background_path' => $setting->background_path,
                'layout_config' => $setting->layout_config,
                'header_text' => $setting->header_text,
                QrPrintablePageBackgroundPreset::LAYOUT_KEY => $data->presetKey,
            ],
        );

        return $tenant->fresh()->load('qrStickerSheetSettings');
    }
}
