<?php

declare(strict_types=1);

namespace App\Actions\Team\Concerns;

use App\Models\TenantQrStickerSheetSetting;
use App\Support\Qr\QrStickerSheetTemplate;

trait ResolvesTenantQrStickerSheetSetting
{
    private function findTenantQrStickerSheetSetting(
        int $tenantId,
        QrStickerSheetTemplate $template,
    ): ?TenantQrStickerSheetSetting {
        return TenantQrStickerSheetSetting::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('template', $template->value)
            ->first();
    }
}
