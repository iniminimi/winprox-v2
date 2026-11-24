<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Enums\SenseMetric;
use App\Http\Requests\Sense\UpdateSenseBandsRequest;
use App\Models\SensePoint;
use App\Support\Audit\AuditRecorder;

class UpdateSenseBandsAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(SensePoint $point, array $input, int $tenantId, ?int $actorUserId = null): SensePoint
    {
        $validated = UpdateSenseBandsRequest::validatePayload($input);
        $metric = SenseMetric::from((string) $validated['metric']);

        $changes = match ($metric) {
            SenseMetric::Temperature => [
                'temperature_low' => round((float) $validated['low'], 1),
                'temperature_target' => round((float) $validated['target'], 1),
                'temperature_high' => round((float) $validated['high'], 1),
            ],
            SenseMetric::Humidity => [
                'humidity_low' => round((float) $validated['low'], 1),
                'humidity_target' => round((float) $validated['target'], 1),
                'humidity_high' => round((float) $validated['high'], 1),
            ],
            SenseMetric::Co2 => [
                'co2_high' => (int) $validated['high'],
            ],
            SenseMetric::Voc => [
                'voc_high' => (int) $validated['high'],
            ],
            SenseMetric::Stale => [],
        };

        if ($changes === []) {
            return $point;
        }

        $point->fill($changes);
        $point->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'sense_point.bands_updated',
            modelType: SensePoint::class,
            modelId: (int) $point->id,
            payload: $changes,
        );

        return $point->fresh() ?? $point;
    }
}
