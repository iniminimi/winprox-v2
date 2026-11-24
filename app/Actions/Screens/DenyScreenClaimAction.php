<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Enums\ScreenClaimStatus;
use App\Models\ScreenClaim;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class DenyScreenClaimAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(ScreenClaim $claim, int $tenantId, ?int $actorUserId): void
    {
        if ((int) $claim->tenant_id !== $tenantId || $claim->status !== ScreenClaimStatus::Pending) {
            throw ValidationException::withMessages([
                'claim' => [__('screen.errors.not_pending')],
            ]);
        }

        $claim->update([
            'status' => ScreenClaimStatus::Denied,
            'denied_reason' => 'admin',
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'screen.claim_denied',
            modelType: ScreenClaim::class,
            modelId: $claim->id,
            payload: [
                'screen_id' => $claim->screen_id,
                'reason' => 'admin',
            ],
        );
    }
}
