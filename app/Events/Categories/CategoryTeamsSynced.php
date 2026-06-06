<?php

namespace App\Events\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CategoryTeamsSynced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param array<int, int> $teamIds
     */
    public function __construct(
        public Category $category,
        public array $teamIds,
        public ?User $actor = null,
    ) {
    }
}
