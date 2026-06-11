<?php

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Audit\AuditRecorder;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\TenantQrStickerBackgroundStorage;
use Illuminate\Http\UploadedFile;

class UploadTenantQrStickerSheetBackgroundAction
{
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
        $existing = TenantQrStickerSheetSetting::query()
            ->where('tenant_id', $tenant->id)
            ->where('template', $template->value)
            ->first();

        $this->backgroundStorage->delete($existing?->background_path);

        $path = $this->backgroundStorage->store($background, (int) $tenant->id, $template);

        $setting = TenantQrStickerSheetSetting::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'template' => $template->value,
            ],
            [
                'background_path' => $path,
            ],
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
