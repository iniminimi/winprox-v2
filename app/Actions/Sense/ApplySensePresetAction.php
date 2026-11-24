<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Http\Requests\Sense\ApplySensePresetRequest;
use App\Models\SensePoint;
use App\Support\Audit\AuditRecorder;

class ApplySensePresetAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(SensePoint $point, string $preset, int $tenantId, ?int $actorUserId = null): SensePoint
    {
        $validated = ApplySensePresetRequest::validatePayload(['preset' => $preset]);
        $values = config('sense.presets.'.$validated['preset']);

        $changes = [
            'temperature_low' => round((float) $values['temperature']['low'], 1),
            'temperature_target' => round((float) $values['temperature']['target'], 1),
            'temperature_high' => round((float) $values['temperature']['high'], 1),
            'humidity_low' => round((float) $values['humidity']['low'], 1),
            'humidity_target' => round((float) $values['humidity']['target'], 1),
            'humidity_high' => round((float) $values['humidity']['high'], 1),
        ];

        $point->fill($changes);
        $point->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'sense_point.bands_updated',
            modelType: SensePoint::class,
            modelId: (int) $point->id,
            payload: ['preset' => $validated['preset']] + $changes,
        );

        return $point->fresh() ?? $point;
    }
}
