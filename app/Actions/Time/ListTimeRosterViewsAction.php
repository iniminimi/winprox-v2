<?php

namespace App\Actions\Time;

use App\Data\Time\TimeRosterViewAttention;
use App\Models\AuditLog;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Collection;

class ListTimeRosterViewsAction
{
    /**
     * Raadplegingen van de evacuatielijst vandaag (tenant-tijdzone).
     *
     * @return Collection<int, TimeRosterViewAttention>
     */
    public function handle(int $tenantId, ?int $teamId = null, ?string $search = null): Collection
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        $needle = mb_strtolower(trim((string) $search));
        $from = now()->startOfDay();
        $until = $from->copy()->addDay();

        $logs = AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where('action', 'time.roster.viewed')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $until)
            ->orderByDesc('created_at')
            ->get();

        $workerIds = $logs
            ->map(fn (AuditLog $log) => (int) ($log->payload['worker_id'] ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $workers = $workerIds === []
            ? collect()
            : Worker::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $workerIds)
                ->with('team.translations')
                ->get()
                ->keyBy('id');

        return $logs
            ->map(function (AuditLog $log) use ($workers) {
                $payload = is_array($log->payload) ? $log->payload : [];
                $workerId = (int) ($payload['worker_id'] ?? 0);
                $worker = $workers->get($workerId);
                $first = trim((string) ($payload['first_name'] ?? $worker?->first_name ?? ''));
                $last = trim((string) ($payload['last_name'] ?? $worker?->last_name ?? ''));
                $display = trim($first.' '.$last);
                if ($display === '') {
                    $display = $worker?->displayName() ?? '—';
                }

                $viewedAt = $log->created_at ?? now();
                $payloadViewedAt = $payload['viewed_at'] ?? null;
                if (is_string($payloadViewedAt) && $payloadViewedAt !== '') {
                    try {
                        $viewedAt = \Carbon\Carbon::parse($payloadViewedAt);
                    } catch (\Throwable) {
                        // keep created_at
                    }
                }

                return new TimeRosterViewAttention(
                    auditId: (int) $log->id,
                    workerId: $workerId,
                    displayName: $display,
                    teamId: $worker?->internal_team_id !== null ? (int) $worker->internal_team_id : null,
                    teamName: $worker?->team?->localizedName(),
                    viewedAt: $viewedAt,
                );
            })
            ->filter(function (TimeRosterViewAttention $view) use ($teamId, $needle) {
                if ($teamId !== null && (int) $view->teamId !== (int) $teamId) {
                    return false;
                }

                if ($needle !== '' && ! str_contains(mb_strtolower($view->displayName), $needle)) {
                    return false;
                }

                return true;
            })
            ->values();
    }
}
