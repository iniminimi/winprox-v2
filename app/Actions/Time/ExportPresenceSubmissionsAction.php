<?php

declare(strict_types=1);

namespace App\Actions\Time;

use App\Data\Reports\ListExportResult;
use App\Enums\PresenceSubmissionStatus;
use App\Models\PresenceSubmission;
use App\Support\Reports\ListExportLimit;

class ExportPresenceSubmissionsAction
{
    /**
     * @return ListExportResult<PresenceSubmission>
     */
    public function handle(
        int $tenantId,
        ?PresenceSubmissionStatus $status = null,
        string $search = '',
    ): ListExportResult {
        $limit = ListExportLimit::MAX;
        $search = trim($search);

        $rows = PresenceSubmission::query()
            ->where('tenant_id', $tenantId)
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search): void {
                $q->whereHas('worker', function ($workerQuery) use ($search): void {
                    $workerQuery->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%');
                });
            })
            ->with(['worker', 'location'])
            ->orderByDesc('registration_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $truncated = $rows->count() > $limit;
        if ($truncated) {
            $rows = $rows->take($limit)->values();
        }

        return new ListExportResult($rows, $truncated, $limit);
    }
}
