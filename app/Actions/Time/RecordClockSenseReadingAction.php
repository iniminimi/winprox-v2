<?php

declare(strict_types=1);

namespace App\Actions\Time;

use App\Actions\Sense\RecordSenseReadingAction;
use App\Data\Sense\RecordSenseReadingData;
use App\Models\ClockPoint;
use App\Models\Screen;
use App\Models\SensePoint;
use InvalidArgumentException;

class RecordClockSenseReadingAction
{
    public function __construct(private RecordSenseReadingAction $record) {}

    /**
     * @return array{reading: \App\Models\SenseReading, created: bool}
     */
    public function handle(ClockPoint $clockPoint, RecordSenseReadingData $data): array
    {
        $screen = Screen::query()
            ->where('clock_point_id', $clockPoint->id)
            ->where('has_sense', true)
            ->first();

        $point = $screen instanceof Screen
            ? SensePoint::query()->where('screen_id', $screen->id)->first()
            : null;

        if (! $point instanceof SensePoint) {
            throw new InvalidArgumentException('sense_disabled');
        }

        return $this->record->handle($point, $data);
    }
}
