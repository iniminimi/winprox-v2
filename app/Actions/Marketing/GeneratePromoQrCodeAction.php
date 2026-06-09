<?php

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Support\Qr\QrCodePngWriter;

/**
 * Genereert een hoge-resolutie promotionele QR-code (PNG) voor externe drukwerkdoeleinden.
 */
class GeneratePromoQrCodeAction
{
    public function __construct(private LogAuditAction $logAudit) {}

    public function handle(int $size, string $targetUrl, int $actorUserId): string
    {
        $pngData = QrCodePngWriter::writeStringWithWinproxLogo($targetUrl, $size);

        $this->logAudit->handle(
            userId: $actorUserId,
            tenantId: null,
            action: 'marketing.promo_qr_generated',
            payload: ['target_url' => $targetUrl, 'size' => $size],
        );

        return $pngData;
    }
}
