<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Models\Issue;
use Illuminate\Support\Collection;

final class IssueExportTable
{
    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return [
            __('reports.columns.id'),
            __('reports.columns.status'),
            __('reports.columns.description'),
            __('reports.columns.location'),
            __('reports.columns.unit'),
            __('reports.columns.reporter'),
            __('reports.columns.created_at'),
            __('reports.columns.approved_at'),
            __('reports.columns.recurring'),
            __('reports.columns.teams'),
            __('reports.columns.source'),
        ];
    }

    /**
     * @param  Collection<int, Issue>  $issues
     * @return Collection<int, list<string>>
     */
    public static function rows(Collection $issues): Collection
    {
        return $issues->map(function (Issue $issue): array {
            $teams = $issue->tasks
                ->map(fn ($task) => $task->team?->localizedName())
                ->filter()
                ->unique()
                ->implode(', ');

            return [
                (string) $issue->id,
                __($issue->status->labelKey()),
                $issue->localizedDescription(),
                (string) ($issue->location?->name ?? ''),
                (string) ($issue->unit?->localizedName() ?? $issue->unit?->name ?? ''),
                (string) ($issue->reporter_name ?? ''),
                $issue->created_at?->format('Y-m-d H:i') ?? '',
                $issue->approved_at?->format('Y-m-d H:i') ?? '',
                $issue->is_recurring ? __('reports.yes') : __('reports.no'),
                $teams,
                $issue->source ? __($issue->source->labelKey()) : '',
            ];
        });
    }
}
