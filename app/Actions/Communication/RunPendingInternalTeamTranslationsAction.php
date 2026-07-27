<?php

namespace App\Actions\Communication;

use App\Enums\InternalTeamTranslationStatus;
use App\Models\InternalTeamTranslation;

class RunPendingInternalTeamTranslationsAction
{
    public function __construct(private TranslateInternalTeamAction $translateTeam) {}

    public function handle(?int $limit = null, ?int $actorUserId = null, ?callable $onProgress = null): int
    {
        $limit = $limit ?? (int) config('ollama.batch_limit', 25);

        $rows = InternalTeamTranslation::query()
            ->where('status', InternalTeamTranslationStatus::Pending)
            ->whereHas('team', fn ($query) => $query->where('is_active', true)->where('name', '!=', ''))
            ->with('team')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        $total = $rows->count();
        $onProgress?->__invoke($processed, $total, null, null);

        foreach ($rows as $row) {
            if ($row->team === null) {
                continue;
            }

            $this->translateTeam->handle($row->team, $row->locale, $actorUserId);
            $processed++;
            $onProgress?->__invoke($processed, $total, $row->id, $row->locale);
        }

        return $processed;
    }
}
