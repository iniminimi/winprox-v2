<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Support\Audit\SummarizeAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListPlatformAuditLogsAction
{
    /**
     * Housekeeping writes that must not appear in the platform activity log.
     *
     * @var list<string>
     */
    public const HIDDEN_ACTIONS = [
        'marketing.promo_campaign_pause_released_complete',
    ];

    public function __construct(
        private SummarizeAuditLog $summarize,
    ) {}

    /**
     * @return array{
     *     rows: LengthAwarePaginator,
     *     summaries: Collection<int, array{title: string, meta: string, context: string|null, action: string}>
     * }
     */
    public function handle(string $search = '', int $page = 1, int $perPage = 50): array
    {
        $search = trim($search);

        $rows = AuditLog::query()
            ->with(['tenant', 'user'])
            ->whereNotIn('action', self::HIDDEN_ACTIONS)
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $actionCodes = $this->summarize->actionCodesMatchingLabel($search);

                $query->where(function ($subQuery) use ($like, $actionCodes): void {
                    $subQuery->where('action', 'like', $like)
                        ->orWhere('model_type', 'like', $like)
                        ->orWhereRaw('CAST(model_id AS CHAR) LIKE ?', [$like])
                        ->orWhereHas('tenant', function ($tenantQuery) use ($like): void {
                            $tenantQuery->where('name', 'like', $like);
                        })
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

    /**
     * @return list<array{id: int, title: string, meta: string, context: string|null, action: string}>
     */
    public function recentSummaries(int $limit = 8): array
    {
        return AuditLog::query()
            ->with(['tenant', 'user'])
            ->whereNotIn('action', self::HIDDEN_ACTIONS)
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function (AuditLog $log): array {
                return array_merge(
                    ['id' => $log->id],
                    $this->summarize->handle($log),
                );
            })
            ->all();
    }

}
