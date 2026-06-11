<?php

namespace App\Actions\Team;

use App\Actions\Team\Concerns\ResolvesTenantQrStickerSheetSetting;
use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Audit\AuditRecorder;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\TenantQrStickerBackgroundStorage;
use Illuminate\Http\UploadedFile;

class UploadTenantQrStickerSheetBackgroundAction
{
    use ResolvesTenantQrStickerSheetSetting;

    public function __construct(
        private TenantQrStickerBackgroundStorage $backgroundStorage,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        Tenant $tenant,
        QrStickerSheetTemplate $template,
        UploadedFile $background,
        ?int $actorUserId = null,
    ): Tenant {
        $existing = $this->findTenantQrStickerSheetSetting((int) $tenant->id, $template);

        $this->backgroundStorage->delete($existing?->background_path);

        $path = $this->backgroundStorage->store($background, (int) $tenant->id, $template);

        $attributes = ['background_path' => $path];
        if ($existing !== null) {
            $attributes['header_text'] = $existing->header_text;
            $attributes['layout_config'] = $existing->layout_config;
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
            action: 'tenant.qr_sticker_sheet_background_uploaded',
            modelType: TenantQrStickerSheetSetting::class,
            modelId: (int) $setting->id,
            payload: [
                'template' => $template->value,
                'background_path' => $setting->background_path,
            ],
        );

        return $tenant->fresh()->load('qrStickerSheetSettings');
    }
}
