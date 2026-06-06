<?php

namespace App\Data\Units;

class SyncUnitTeamsData
{
    public function __construct(
        /** @var array<int> */
        public array $teams,
    ) {
    }

    public static function fromRequest(array $data): self
    {
        return new self(
            teams: $data['teams'] ?? [],
        );
    }
}
