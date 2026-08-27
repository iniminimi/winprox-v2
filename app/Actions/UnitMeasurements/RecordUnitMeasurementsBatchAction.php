<?php

declare(strict_types=1);

namespace App\Actions\UnitMeasurements;

use App\Data\UnitMeasurements\RecordUnitMeasurementData;
use App\Enums\UnitMeasurementSource;
use App\Models\Unit;
use App\Models\UnitMeasurement;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordUnitMeasurementsBatchAction
{
    public function __construct(private RecordUnitMeasurementAction $record) {}

    /**
     * @param  list<array{
     *     unit_measure_field_id: int,
     *     value_numeric?: float|null,
     *     value_boolean?: bool|null,
     *     value_string?: string|null
     * }>  $entries
     * @return list<UnitMeasurement>
     */
    public function handle(
        Unit $unit,
        array $entries,
        int $tenantId,
        UnitMeasurementSource $source,
        CarbonImmutable $recordedAt,
        ?Worker $worker = null,
        ?int $actorUserId = null,
    ): array {
        if ($entries === []) {
            throw ValidationException::withMessages([
                'measurements' => [__('unit_measurements.errors.batch_empty')],
            ]);
        }

        return DB::transaction(function () use ($unit, $entries, $tenantId, $source, $recordedAt, $worker, $actorUserId): array {
            $created = [];

            foreach ($entries as $entry) {
                $created[] = $this->record->handle(
                    unit: $unit,
                    data: new RecordUnitMeasurementData(
                        unitMeasureFieldId: (int) $entry['unit_measure_field_id'],
                        source: $source,
                        recordedAt: $recordedAt,
                        valueNumeric: $entry['value_numeric'] ?? null,
                        valueBoolean: $entry['value_boolean'] ?? null,
                        valueString: $entry['value_string'] ?? null,
                    ),
                    tenantId: $tenantId,
                    worker: $worker,
                    actorUserId: $actorUserId,
                );
            }

            return $created;
        });
    }
}
