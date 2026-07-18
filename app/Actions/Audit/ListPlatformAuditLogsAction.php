<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Support\Audit\SummarizeAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListPlatformAuditLogsAction
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
    public function handle(string $search = '', int $page = 1, int $perPage = 50): array
    {
        $search = trim($search);

        $rows = AuditLog::query()
            ->with(['tenant', 'user'])
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $actionCodes = $this->actionCodesMatchingLabel($search);

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

    /**
     * @return list<string>
     */
    private function actionCodesMatchingLabel(string $search): array
    {
        $needle = mb_strtolower(trim($search));
        if ($needle === '') {
            return [];
        }

        /** @var mixed $actions */
        $actions = __('audit.actions');
        if (! is_array($actions)) {
            return [];
        }

        $matched = [];
        foreach ($actions as $code => $label) {
            if (! is_string($code) || ! is_string($label)) {
                continue;
            }
            if (str_contains(mb_strtolower($label), $needle) || str_contains(mb_strtolower($code), $needle)) {
                $matched[] = $code;
            }
        }

        return $matched;
    }
}
