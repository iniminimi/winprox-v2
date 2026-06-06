<?php

namespace App\Data\Categories;

readonly class SyncCategoryTeamsData
{
    /** @param array<int, int> $team_ids */
    public function __construct(
        public array $team_ids,
    ) {
    }

    /** @param array{teams?: array<int, int>} $input */
    public static function fromRequest(array $input): self
    {
        return new self(
            team_ids: (array) ($input['teams'] ?? []),
        );
    }
}
