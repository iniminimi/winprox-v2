<?php

declare(strict_types=1);

namespace App\Actions\Issues;

use App\Models\Issue;
use App\Models\IssueRoundStop;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Haalt units uit alle inspectieronde-stops (bv. na uitschakelen van unit checks).
 * Rondes met minder dan 2 stops over houden geen stops meer.
 */
class RemoveUnitsFromInspectionRoundsAction
{
    /**
     * @param  list<int>  $unitIds
     */
    public function handle(array $unitIds, int $tenantId, ?User $actor = null): void
    {
        $uniqueIds = array_values(array_unique(array_map('intval', $unitIds)));
        if ($uniqueIds === []) {
            return;
        }

        DB::transaction(function () use ($uniqueIds, $tenantId): void {
            $issueIds = IssueRoundStop::query()
                ->whereIn('unit_id', $uniqueIds)
                ->whereHas('issue', fn ($q) => $q->where('tenant_id', $tenantId))
                ->pluck('issue_id')
                ->unique()
                ->values()
                ->all();

            if ($issueIds === []) {
                return;
            }

            IssueRoundStop::query()
                ->whereIn('unit_id', $uniqueIds)
                ->whereIn('issue_id', $issueIds)
                ->delete();

            foreach ($issueIds as $issueId) {
                $this->normalizeIssueStops((int) $issueId);
            }
        });
    }

    /**
     * Verwijder stops op één melding waarvan de unit geen unit checks meer toelaat.
     */
    public function handleForIssue(Issue $issue): Issue
    {
        $issue->loadMissing(['roundStops.unit.category']);

        $invalidIds = $issue->roundStops
            ->filter(fn (IssueRoundStop $stop): bool => $stop->unit === null || ! $stop->unit->allowsUnitChecks())
            ->pluck('unit_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($invalidIds === []) {
            return $issue;
        }

        $this->handle($invalidIds, (int) $issue->tenant_id);

        return $issue->fresh(['roundStops.unit.translations', 'roundStops.unit.category']) ?? $issue;
    }

    private function normalizeIssueStops(int $issueId): void
    {
        $remaining = IssueRoundStop::query()
            ->where('issue_id', $issueId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($remaining->count() < 2) {
            IssueRoundStop::query()->where('issue_id', $issueId)->delete();

            return;
        }

        foreach ($remaining->values() as $index => $stop) {
            if ((int) $stop->sort_order !== $index) {
                $stop->forceFill(['sort_order' => $index])->save();
            }
        }
    }
}
