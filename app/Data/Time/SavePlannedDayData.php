<?php

namespace App\Data\Time;

final class SavePlannedDayData
{
    /**
     * Blocks are the complete desired state of one worker×dag.
     * Per blok: bestaand blok heeft 'id', nieuw blok heeft geen 'id';
     * ontbrekende bestaande ids worden verwijderd.
     *
     * @param  list<array{id?: int|null, shift_type_id?: int|null, start_time?: ?string, end_time?: ?string, break_minutes?: int|null, unit_id?: int|null, description?: ?string}>  $blocks
     */
    public function __construct(
        public int $workerId,
        public string $date,
        public array $blocks,
    ) {}
}
