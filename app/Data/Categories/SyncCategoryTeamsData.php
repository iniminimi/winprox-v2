<?php

namespace App\Data\Categories;

readonly class SyncCategoryTeamsData
{
    /**
     * @param array<int, array{id: int, is_primary: bool}> $teams
     */
    public function __construct(
        public array $teams,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            teams: $data['teams'] ?? [],
        );
    }
}
