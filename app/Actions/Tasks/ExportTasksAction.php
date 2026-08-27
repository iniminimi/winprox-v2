<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Data\Reports\ListExportResult;
use App\Data\Tasks\ExportTasksFilterData;
use App\Enums\IssueTranslationStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskTranslationStatus;
use App\Enums\UnitTranslationStatus;
use App\Models\Task;
use App\Support\Reports\ListExportLimit;

class ExportTasksAction
{
    /**
     * @return ListExportResult<Task>
     */
    public function handle(int $tenantId, ExportTasksFilterData $filters): ListExportResult
    {
        $query = Task::query()
            ->where('tenant_id', $tenantId)
            ->forApprovedIssue()
            ->with(['issue.location', 'issue.unit.translations', 'issue.translations', 'translations', 'team.translations'])
            ->when($filters->status !== '', fn ($q) => $q->where('status', $filters->status))
            ->when($filters->status === '', fn ($q) => $q->where('status', '!=', TaskStatus::Closed))
            ->when($filters->priority !== '', fn ($q) => $q->where('priority', $filters->priority))
            ->when($filters->teamId, fn ($q) => $q->where('internal_team_id', $filters->teamId))
            ->when($filters->recurringOnly, function ($q) {
                $q->where(function ($query) {
                    $query->where('is_recurring_cycle', true)
                        ->orWhereHas('issue', fn ($issue) => $issue->where('is_recurring', true));
                });
            })
            ->when(trim($filters->search) !== '', function ($q) use ($filters) {
                $term = '%'.trim($filters->search).'%';
                $q->where(function ($query) use ($term) {
                    $query->where('description', 'like', $term)
                        ->orWhereHas('translations', fn ($translation) => $translation
                            ->where('status', TaskTranslationStatus::Completed)
                            ->where('description', 'like', $term))
                        ->orWhereHas('issue', fn ($issue) => $issue
                            ->where('description', 'like', $term)
                            ->orWhere('reporter_name', 'like', $term)
                            ->orWhereHas('translations', fn ($translation) => $translation
                                ->where('status', IssueTranslationStatus::Completed)
                                ->where('description', 'like', $term))
                            ->orWhereHas('location', fn ($loc) => $loc->where(function ($locationQuery) use ($term) {
                                $locationQuery->where('name', 'like', $term)
                                    ->orWhere('street', 'like', $term)
                                    ->orWhere('house_number', 'like', $term)
                                    ->orWhere('postal_code', 'like', $term)
                                    ->orWhere('city', 'like', $term)
                                    ->orWhere('address', 'like', $term);
                            }))
                            ->orWhereHas('unit', fn ($unit) => $unit->where(function ($unitQuery) use ($term) {
                                $unitQuery->where('name', 'like', $term)
                                    ->orWhereHas('translations', fn ($translation) => $translation
                                        ->where('status', UnitTranslationStatus::Completed)
                                        ->where(function ($translatedUnit) use ($term) {
                                            $translatedUnit->where('name', 'like', $term)
                                                ->orWhere('description', 'like', $term);
                                        }));
                            })));
                });
            })
            ->orderByDesc('id');

        $limit = ListExportLimit::MAX;
        $rows = $query->limit($limit + 1)->get();
        $truncated = $rows->count() > $limit;
        if ($truncated) {
            $rows = $rows->take($limit)->values();
        }

        return new ListExportResult($rows, $truncated, $limit);
    }
}
