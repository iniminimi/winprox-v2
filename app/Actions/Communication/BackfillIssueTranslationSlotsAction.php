<?php

namespace App\Actions\Communication;

use App\Models\Issue;
use App\Models\IssueTranslation;

class BackfillIssueTranslationSlotsAction
{
    public function __construct(private EnsureIssueTranslationSlotsAction $ensureSlots) {}

    /**
     * @return array{issues: int, slots_created: int}
     */
    public function handle(?int $tenantId = null): array
    {
        $issuesProcessed = 0;
        $slotsCreated = 0;

        Issue::query()
            ->whereNotNull('approved_at')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(100, function ($issues) use (&$issuesProcessed, &$slotsCreated): void {
                foreach ($issues as $issue) {
                    $before = IssueTranslation::query()
                        ->where('issue_id', $issue->id)
                        ->count();

                    $this->ensureSlots->handle($issue);

                    $after = IssueTranslation::query()
                        ->where('issue_id', $issue->id)
                        ->count();

                    $issuesProcessed++;
                    $slotsCreated += max(0, $after - $before);
                }
            });

        return [
            'issues' => $issuesProcessed,
            'slots_created' => $slotsCreated,
        ];
    }
}
