<?php

declare(strict_types=1);

namespace App\Actions\Issues;

use App\Data\Issues\ExportIssuesFilterData;
use App\Data\Reports\ListExportResult;
use App\Enums\IssueTranslationStatus;
use App\Enums\TaskStatus;
use App\Enums\UnitTranslationStatus;
use App\Models\Issue;
use App\Support\Reports\ListExportLimit;

class ExportIssuesAction
{
    /**
     * @return ListExportResult<Issue>
     */
    public function handle(int $tenantId, ExportIssuesFilterData $filters): ListExportResult
    {
        $query = Issue::query()
            ->where('tenant_id', $tenantId)
            ->with(['location.translations', 'unit.translations', 'tasks.team.translations', 'translations', 'roundStops'])
            ->when($filters->status !== '', fn ($q) => $q->where('status', $filters->status))
            ->when($filters->status === '', fn ($q) => $q->where('status', '!=', TaskStatus::Closed))
            ->when($filters->teamId, fn ($q) => $q->whereHas(
                'tasks',
                fn ($t) => $t->where('internal_team_id', $filters->teamId)
            ))
            ->when($filters->recurringOnly, fn ($q) => $q->where('is_recurring', true))
            ->when($filters->inspectionRoundOnly, function ($q) {
                $q->whereIn('id', function ($sub) {
                    $sub->select('issue_id')
                        ->from('issue_round_stops')
                        ->groupBy('issue_id')
                        ->havingRaw('COUNT(*) >= 2');
                });
            })
            ->when($filters->unitId, fn ($q) => $q->where('unit_id', $filters->unitId))
            ->when(trim($filters->search) !== '', function ($q) use ($filters) {
                $term = '%'.trim($filters->search).'%';
                $q->where(function ($query) use ($term) {
                    $query->where('description', 'like', $term)
                        ->orWhere('reporter_name', 'like', $term)
                        ->orWhere('reporter_contact', 'like', $term)
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
                        }));
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
