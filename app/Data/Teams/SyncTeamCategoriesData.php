<?php

namespace App\Data\Teams;

readonly class SyncTeamCategoriesData
{
    /**
     * @param array<int, array{id: int, is_primary: bool}> $categories
     */
    public function __construct(
        public array $categories,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            categories: $data['categories'] ?? [],
        );
    }
}
