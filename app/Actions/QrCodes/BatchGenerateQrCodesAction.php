<?php

namespace App\Actions\QrCodes;

use App\Models\QrCode;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Collection;

class BatchGenerateQrCodesAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @return Collection<int, QrCode>
     */
    public function handle(int $count, int $tenantId, ?int $actorUserId = null): Collection
    {
        if ($count < 1 || $count > 1000) {
            throw new \InvalidArgumentException('Count must be between 1 and 1000');
        }

        // First, try to reuse existing unassigned QR codes for this tenant
        $existingQrCodes = QrCode::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', \App\Enums\QrCodeStatus::Unassigned)
            ->whereNull('unit_id')
            ->orderBy('id')
            ->limit($count)
            ->get();

        $needed = $count - $existingQrCodes->count();
        $qrCodes = $existingQrCodes;

        // Generate new QR codes only if we need more
        if ($needed > 0) {
            for ($i = 0; $i < $needed; $i++) {
                $qrCodes->push(
                    QrCode::create([
                        'tenant_id' => $tenantId,
                        'status' => \App\Enums\QrCodeStatus::Unassigned,
                    ])
                );
            }
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'qr_codes.batch_generated',
            modelType: QrCode::class,
            modelId: null,
            payload: [
                'count' => $count,
                'reused' => $existingQrCodes->count(),
                'generated' => $needed,
                'qr_code_ids' => $qrCodes->pluck('id')->toArray(),
            ],
        );

        return $qrCodes;
    }
}
