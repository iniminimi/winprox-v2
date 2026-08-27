<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Models\UnitCheck;
use Illuminate\Support\Collection;

final class UnitCheckExportTable
{
    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return [
            __('reports.columns.id'),
            __('reports.columns.checked_at'),
            __('reports.columns.result'),
            __('reports.columns.location'),
            __('reports.columns.unit'),
            __('reports.columns.worker'),
            __('reports.columns.team'),
            __('reports.columns.checklist'),
        ];
    }

    /**
     * @param  Collection<int, UnitCheck>  $checks
     * @return Collection<int, list<string>>
     */
    public static function rows(Collection $checks): Collection
    {
        return $checks->map(function (UnitCheck $check): array {
            $checklist = is_array($check->checklist_items)
                ? implode(', ', $check->checklist_items)
                : '';

            return [
                (string) $check->id,
                $check->checked_at?->format('Y-m-d H:i') ?? '',
                __('unit_checks.result.'.$check->result->value),
                (string) ($check->location?->name ?? ''),
                (string) ($check->unit?->name ?? ''),
                (string) ($check->worker?->displayName() ?? ''),
                (string) ($check->team?->localizedName() ?? ''),
                $checklist,
            ];
        });
    }
}
