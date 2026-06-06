<?php

namespace App\Events\Teams;

use App\Models\InternalTeam;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamCategoriesSynced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param array<int, int> $categoryIds
     */
    public function __construct(
        public InternalTeam $team,
        public array $categoryIds,
        public ?User $actor = null,
    ) {
    }
}
