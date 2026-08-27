<?php

namespace App\Actions\Communication;

use App\Enums\AnnouncementTranslationStatus;
use App\Enums\DocumentTranslationStatus;
use App\Enums\IssueTranslationStatus;
use App\Enums\TaskTranslationStatus;
use App\Models\AnnouncementTranslation;
use App\Models\DocumentTranslation;
use App\Models\IssueTranslation;
use App\Models\TaskTranslation;

/**
 * Puts failed description translations back on pending so local/Ollama runs can retry.
 * Old logic marked identical provider output as failed and never retried — UI then showed
 * «Not translated yet» forever.
 */
class RequeueFailedDescriptionTranslationsAction
{
    /**
     * @return array{issues: int, tasks: int, announcements: int, documents: int, total: int}
     */
    public function handle(): array
    {
        $issues = IssueTranslation::query()
            ->where('status', IssueTranslationStatus::Failed)
            ->update([
                'status' => IssueTranslationStatus::Pending->value,
                'description' => null,
            ]);

        $tasks = TaskTranslation::query()
            ->where('status', TaskTranslationStatus::Failed)
            ->update([
                'status' => TaskTranslationStatus::Pending->value,
                'description' => null,
            ]);

        $announcements = AnnouncementTranslation::query()
            ->where('status', AnnouncementTranslationStatus::Failed)
            ->update([
                'status' => AnnouncementTranslationStatus::Pending->value,
                'description' => null,
            ]);

        $documents = DocumentTranslation::query()
            ->where('status', DocumentTranslationStatus::Failed)
            ->update([
                'status' => DocumentTranslationStatus::Pending->value,
                'description' => null,
            ]);

        return [
            'issues' => $issues,
            'tasks' => $tasks,
            'announcements' => $announcements,
            'documents' => $documents,
            'total' => $issues + $tasks + $announcements + $documents,
        ];
    }
}
