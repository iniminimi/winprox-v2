<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Data\Sense\RecordSenseReadingData;
use App\Models\Screen;
use App\Models\SensePoint;
use InvalidArgumentException;

class RecordUnitScreenSenseReadingAction
{
    public function __construct(private RecordSenseReadingAction $record) {}

    /**
     * @return array{reading: \App\Models\SenseReading, created: bool}
     */
    public function handle(Screen $screen, RecordSenseReadingData $data): array
    {
        $point = SensePoint::query()->where('screen_id', $screen->id)->first();

        if (! $screen->has_sense || ! $point instanceof SensePoint) {
            throw new InvalidArgumentException('sense_disabled');
        }

        return $this->record->handle($point, $data);
    }
}
