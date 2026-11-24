<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Models\SensePoint;
use App\Support\Audit\AuditRecorder;

class RotateSensePointTokenAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @return array{point: SensePoint, plain_token: string}
     */
    public function handle(SensePoint $point, int $tenantId, ?int $actorUserId = null): array
    {
        $token = SensePoint::issueToken();
        $point->token_hash = $token['hash'];
        $point->token_prefix = $token['prefix'];
        $point->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'sense_point.token_rotated',
            modelType: SensePoint::class,
            modelId: (int) $point->id,
            payload: ['token_prefix' => $point->token_prefix],
        );

        return [
            'point' => $point,
            'plain_token' => $token['plain'],
        ];
    }
}
