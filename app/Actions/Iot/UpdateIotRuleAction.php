<?php

declare(strict_types=1);

namespace App\Actions\Iot;

use App\Enums\IotRuleOperator;
use App\Enums\TaskPriority;
use App\Models\InternalTeam;
use App\Models\IotRule;
use App\Models\IotSensor;
use App\Support\Audit\AuditRecorder;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Validation\ValidationException;

class UpdateIotRuleAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{
     *     iot_sensor_id: int,
     *     name: string,
     *     operator: string,
     *     threshold: float|int|string,
     *     description: string,
     *     internal_team_id?: int|null,
     *     priority?: string|null
     * }  $data
     */
    public function handle(IotRule $rule, array $data, int $tenantId, ?int $actorUserId = null): IotRule
    {
        $sensor = IotSensor::query()
            ->where('tenant_id', $tenantId)
            ->find($data['iot_sensor_id']);

        if ($sensor === null) {
            throw ValidationException::withMessages([
                'iot_sensor_id' => [__('iot.errors.sensor_invalid')],
            ]);
        }

        $teamId = filled($data['internal_team_id'] ?? null) ? (int) $data['internal_team_id'] : null;
        if ($teamId !== null) {
            $teamExists = InternalTeam::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($teamId)
                ->exists();
            if (! $teamExists) {
                throw ValidationException::withMessages([
                    'internal_team_id' => [__('iot.errors.team_invalid')],
                ]);
            }
        }

        $description = mb_substr((string) $data['description'], 0, TextDescriptionLimits::MAX);

        $rule->forceFill([
            'iot_sensor_id' => $sensor->id,
            'name' => (string) $data['name'],
            'operator' => IotRuleOperator::from((string) $data['operator']),
            'threshold' => (float) $data['threshold'],
            'internal_team_id' => $teamId,
            'priority' => TaskPriority::tryFrom((string) ($data['priority'] ?? TaskPriority::Prio2->value))
                ?? TaskPriority::Prio2,
            'description' => $description,
        ])->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'iot_rule.updated',
            modelType: IotRule::class,
            modelId: (int) $rule->id,
            payload: [
                'id' => $rule->id,
                'iot_sensor_id' => $sensor->id,
                'operator' => $rule->operator->value,
                'threshold' => (float) $rule->threshold,
                'internal_team_id' => $teamId,
                'priority' => $rule->priority->value,
            ],
        );

        return $rule->fresh();
    }
}
