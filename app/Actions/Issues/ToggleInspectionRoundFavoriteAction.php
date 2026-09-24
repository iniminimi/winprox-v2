<?php

declare(strict_types=1);

namespace App\Actions\Issues;

use App\Models\Issue;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

/**
 * Markeer of demarkeer een inspectieronde als tenant-brede favoriet (recept voor kopiëren).
 */
class ToggleInspectionRoundFavoriteAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Issue $issue, User $actor, bool $favorite): Issue
    {
        if (! $issue->isInspectionRound()) {
            throw ValidationException::withMessages([
                'issue' => [__('issues.errors.round_favorite_not_round')],
            ]);
        }

        $issue->forceFill([
            'is_favorite_round' => $favorite,
        ])->save();

        $this->audit->record(
            userId: $actor->id,
            tenantId: (int) $issue->tenant_id,
            action: $favorite ? 'issue.round_favorited' : 'issue.round_unfavorited',
            modelType: Issue::class,
            modelId: (int) $issue->id,
            payload: ['is_favorite_round' => $favorite],
        );

        return $issue->fresh(['roundStops']) ?? $issue;
    }
}
