<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

final class DashboardIntentHubData
{
    /**
     * @param  list<array{key: string, icon: string, tone: string, title: string, body: string, href: string}>  $tiles
     */
    public function __construct(
        public string $greeting,
        public array $tiles,
    ) {}

    public function isEmpty(): bool
    {
        return $this->tiles === [];
    }
}
