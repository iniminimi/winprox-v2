<?php

declare(strict_types=1);

namespace App\Actions\Iot;

use App\Models\IotRule;
use App\Support\Audit\AuditRecorder;

class SetIotRuleActiveAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(IotRule $rule, bool $isActive, ?int $actorUserId = null): IotRule
    {
        $rule->forceFill(['is_active' => $isActive])->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $rule->tenant_id,
            action: $isActive ? 'iot_rule.activated' : 'iot_rule.deactivated',
            modelType: IotRule::class,
            modelId: (int) $rule->id,
            payload: ['is_active' => $isActive],
        );

        return $rule->fresh();
    }
}
