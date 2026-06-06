<?php

namespace App\Events\Categories;

use App\Contracts\WebhookEvent;
use App\Models\Category;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CategoryTeamsSynced implements WebhookEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Category $category,
        public readonly array $teamIds,
        public readonly int $actorId,
    ) {
    }

    public function webhookEventName(): string
    {
        return 'category_teams_synced';
    }

    public function webhookPayload(): array
    {
        return [
            'category_id' => $this->category->id,
            'category_name' => $this->category->name,
            'team_ids' => $this->teamIds,
            'actor_id' => $this->actorId,
        ];
    }

    public function webhookTenantId(): int
    {
        return $this->category->tenant_id;
    }
}
