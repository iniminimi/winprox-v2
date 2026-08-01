<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Enums\TaskStatus;
use App\Models\Issue;
use Illuminate\Support\Collection;

class ListDashboardRecentIssuesAction
{
    /**
     * @return Collection<int, Issue>
     */
    public function handle(int $tenantId, int $limit = 5): Collection
    {
        return Issue::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', TaskStatus::Closed->value)
            ->with(['location', 'unit.translations', 'tasks.team', 'translations'])
            ->orderByRaw('CASE WHEN approved_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
