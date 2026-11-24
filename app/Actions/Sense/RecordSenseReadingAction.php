<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Data\Sense\RecordSenseReadingData;
use App\Models\SensePoint;
use App\Models\SenseReading;
use Illuminate\Database\UniqueConstraintViolationException;

class RecordSenseReadingAction
{
    /**
     * @return array{reading: SenseReading, created: bool}
     */
    public function handle(SensePoint $point, RecordSenseReadingData $data): array
    {
        $existing = $this->findExisting($point, $data);
        if ($existing instanceof SenseReading) {
            return ['reading' => $existing, 'created' => false];
        }

        try {
            $reading = SenseReading::query()->create([
                'tenant_id' => $point->tenant_id,
                'sense_point_id' => $point->id,
                'temperature' => $data->temperature,
                'humidity' => $data->humidity,
                'co2' => $data->co2,
                'voc' => $data->voc,
                'recorded_at' => $data->recordedAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = $this->findExisting($point, $data);

            return ['reading' => $existing ?? new SenseReading, 'created' => false];
        }

        $latest = $point->last_recorded_at;
        if ($latest === null || $data->recordedAt->greaterThanOrEqualTo($latest)) {
            $point->forceFill([
                'last_temperature' => $data->temperature,
                'last_humidity' => $data->humidity,
                'last_co2' => $data->co2,
                'last_voc' => $data->voc,
                'last_recorded_at' => $data->recordedAt,
            ])->save();
        }

        return ['reading' => $reading, 'created' => true];
    }

    private function findExisting(SensePoint $point, RecordSenseReadingData $data): ?SenseReading
    {
        $found = SenseReading::query()
            ->where('sense_point_id', $point->id)
            ->where('recorded_at', $data->recordedAt)
            ->first();

        return $found instanceof SenseReading ? $found : null;
    }
}
