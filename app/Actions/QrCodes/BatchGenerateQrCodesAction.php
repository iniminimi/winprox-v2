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

        $qrCodes = collect();

        for ($i = 0; $i < $count; $i++) {
            $qrCodes->push(
                QrCode::create([
                    'tenant_id' => $tenantId,
                    'status' => \App\Enums\QrCodeStatus::Unassigned,
                ])
            );
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'qr_codes.batch_generated',
            modelType: QrCode::class,
            modelId: null,
            payload: [
                'count' => $count,
                'qr_code_ids' => $qrCodes->pluck('id')->toArray(),
            ],
        );

        return $qrCodes;
    }
}
