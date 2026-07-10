<?php

namespace App\Actions\Communication;

use App\Enums\AnnouncementTranslationStatus;
use App\Models\AnnouncementTranslation;

class RunPendingAnnouncementTranslationsAction
{
    public function __construct(private TranslateAnnouncementAction $translateAnnouncement) {}

    public function handle(?int $limit = null, ?int $actorUserId = null, ?callable $onProgress = null): int
    {
        $limit = $limit ?? (int) config('ollama.batch_limit', 25);

        $rows = AnnouncementTranslation::query()
            ->where('status', AnnouncementTranslationStatus::Pending)
            ->whereHas('announcement', fn ($query) => $query->where('is_active', true))
            ->with('announcement')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;

        $total = $rows->count();
        $onProgress?->__invoke($processed, $total, null, null);

        foreach ($rows as $row) {
            if ($row->announcement === null) {
                continue;
            }

            $this->translateAnnouncement->handle($row->announcement, $row->locale, $actorUserId);
            $processed++;
            $onProgress?->__invoke($processed, $total, $row->id, $row->locale);
        }

        return $processed;
    }
}
