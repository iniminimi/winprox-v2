<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Support\Audit\SummarizeAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListTenantAuditLogsAction
{
    public function __construct(
        private SummarizeAuditLog $summarize,
    ) {}

    /**
     * @return array{
     *     rows: LengthAwarePaginator,
     *     summaries: Collection<int, array{title: string, meta: string, context: string|null, action: string}>
     * }
     */
    public function handle(int $tenantId, string $search = '', int $page = 1, int $perPage = 50): array
    {
        $search = trim($search);

        $rows = AuditLog::query()
            ->with(['user'])
            ->where('tenant_id', $tenantId)
            ->whereNotIn('action', ListPlatformAuditLogsAction::HIDDEN_ACTIONS)
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $actionCodes = $this->summarize->actionCodesMatchingLabel($search);

                $query->where(function ($subQuery) use ($like, $actionCodes): void {
                    $subQuery->where('action', 'like', $like)
                        ->orWhere('model_type', 'like', $like)
                        ->orWhereRaw('CAST(model_id AS CHAR) LIKE ?', [$like])
                        ->orWhereHas('user', function ($userQuery) use ($like): void {
                            $userQuery->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        });

                    if ($actionCodes !== []) {
                        $subQuery->orWhereIn('action', $actionCodes);
                    }
                });
            })
            ->latest('created_at')
            ->paginate(perPage: $perPage, page: $page);

        $summaries = $rows->getCollection()->mapWithKeys(
            fn (AuditLog $log): array => [$log->id => $this->summarize->handle($log)],
        );

        return [
            'rows' => $rows,
            'summaries' => $summaries,
        ];
    }
}
