<?php

namespace App\Actions\Esg;

use App\Enums\EsgIndicatorType;
use App\Models\EsgIndicator;
use App\Support\Audit\AuditRecorder;

class UpdateEsgIndicatorAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{name: string, type: string|EsgIndicatorType, unit_of_measure?: ?string, thresholds?: ?array, options?: ?list<string>}  $data
     */
    public function handle(EsgIndicator $indicator, array $data, ?int $actorUserId = null): EsgIndicator
    {
        $type = $data['type'] instanceof EsgIndicatorType
            ? $data['type']
            : EsgIndicatorType::from((string) $data['type']);

        $indicator->update([
            'name' => trim($data['name']),
            'type' => $type,
            'unit_of_measure' => $data['unit_of_measure'] ?? null,
            'thresholds' => $data['thresholds'] ?? null,
            'options' => $data['options'] ?? null,
        ]);

        $fresh = $indicator->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'esg_indicator.updated',
            modelType: EsgIndicator::class,
            modelId: (int) $fresh->id,
            payload: [
                'id' => $fresh->id,
                'name' => $fresh->name,
                'type' => $fresh->type->value,
            ],
        );

        return $fresh;
    }
}
