<?php

namespace App\Actions\Esg;

use App\Enums\EsgIndicatorType;
use App\Models\EsgIndicator;
use App\Support\Audit\AuditRecorder;

class CreateEsgIndicatorAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{name: string, type: string|EsgIndicatorType, unit_of_measure?: ?string, thresholds?: ?array, options?: ?list<string>}  $data
     */
    public function handle(int $tenantId, array $data, ?int $actorUserId = null): EsgIndicator
    {
        $type = $data['type'] instanceof EsgIndicatorType
            ? $data['type']
            : EsgIndicatorType::from((string) $data['type']);

        $indicator = EsgIndicator::query()->create([
            'tenant_id' => $tenantId,
            'name' => trim($data['name']),
            'type' => $type,
            'unit_of_measure' => $data['unit_of_measure'] ?? null,
            'is_active' => true,
            'thresholds' => $data['thresholds'] ?? null,
            'options' => $data['options'] ?? null,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'esg_indicator.created',
            modelType: EsgIndicator::class,
            modelId: (int) $indicator->id,
            payload: [
                'id' => $indicator->id,
                'name' => $indicator->name,
                'type' => $indicator->type->value,
            ],
        );

        return $indicator;
    }
}
