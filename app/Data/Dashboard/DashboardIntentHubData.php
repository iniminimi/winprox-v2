<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

final class DashboardIntentHubData
{
    /**
     * @param  list<array<string, mixed>>  $tiles
     * @param  list<array<string, mixed>>  $hiddenTiles
     */
    public function __construct(
        public string $greeting,
        public array $tiles,
        public array $hiddenTiles = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->tiles === [];
    }
}
