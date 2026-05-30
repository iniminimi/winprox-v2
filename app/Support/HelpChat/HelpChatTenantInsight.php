<?php

namespace App\Support\HelpChat;

use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;

class HelpChatTenantInsight
{
    public function __construct(private Tenant $tenant) {}

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $tenantId = $this->tenant->id;

        return [
            'locations' => Location::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->count(),
            'units' => Unit::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->count(),
            'issues' => Issue::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->count(),
            'tasks' => Task::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->count(),
        ];
    }

    public function summaryLine(): string
    {
        $c = $this->counts();

        return __('help.insight.summary', [
            'locations' => $c['locations'],
            'units' => $c['units'],
            'issues' => $c['issues'],
            'tasks' => $c['tasks'],
        ]);
    }
}
