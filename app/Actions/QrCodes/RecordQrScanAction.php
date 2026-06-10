<?php

namespace App\Actions\QrCodes;

use App\Models\QrCode;
use App\Models\QrScan;

class RecordQrScanAction
{
    public function handle(
        QrCode $qrCode,
        ?int $userId,
        ?string $ipAddress,
        ?string $userAgent,
    ): QrCode {
        QrScan::create([
            'qr_code_id' => $qrCode->id,
            'tenant_id' => $qrCode->tenant_id,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'scanned_at' => now(),
        ]);

        $qrCode->update(['last_scanned_at' => now()]);

        return $qrCode->fresh();
    }
}
