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
use App\Support\Translation\TranslationOutputGuard;

/**
 * Re-queues completed description translations that merely echo the source text
 * (Ollama/provider failed silently and copied the original).
 */
class RequeueUntranslatedDescriptionEchoesAction
{
    /**
     * @return array{issues: int, tasks: int, announcements: int, documents: int, total: int}
     */
    public function handle(): array
    {
        $issues = $this->requeueIssues();
        $tasks = $this->requeueTasks();
        $announcements = $this->requeueAnnouncements();
        $documents = $this->requeueDocuments();

        return [
            'issues' => $issues,
            'tasks' => $tasks,
            'announcements' => $announcements,
            'documents' => $documents,
            'total' => $issues + $tasks + $announcements + $documents,
        ];
    }

    private function requeueIssues(): int
    {
        $count = 0;

        IssueTranslation::query()
            ->where('status', IssueTranslationStatus::Completed)
            ->with('issue')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$count): void {
                foreach ($rows as $row) {
                    $issue = $row->issue;
                    if ($issue === null) {
                        continue;
                    }

                    $source = trim((string) $issue->description);
                    $translated = trim((string) ($row->description ?? ''));
                    if (! TranslationOutputGuard::isUntranslatedEcho(
                        $translated,
                        $source,
                        (string) $row->locale,
                        $issue->normalizedOriginalLanguage(),
                    )) {
                        continue;
                    }

                    $row->fill([
                        'description' => null,
                        'status' => IssueTranslationStatus::Pending,
                    ])->save();
                    $count++;
                }
            });

        return $count;
    }

    private function requeueTasks(): int
    {
        $count = 0;

        TaskTranslation::query()
            ->where('status', TaskTranslationStatus::Completed)
            ->with('task')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$count): void {
                foreach ($rows as $row) {
                    $task = $row->task;
                    if ($task === null) {
                        continue;
                    }

                    $source = trim((string) ($task->description ?? ''));
                    $translated = trim((string) ($row->description ?? ''));
                    if (! TranslationOutputGuard::isUntranslatedEcho(
                        $translated,
                        $source,
                        (string) $row->locale,
                        $task->normalizedOriginalLanguage(),
                    )) {
                        continue;
                    }

                    $row->fill([
                        'description' => null,
                        'status' => TaskTranslationStatus::Pending,
                    ])->save();
                    $count++;
                }
            });

        return $count;
    }

    private function requeueAnnouncements(): int
    {
        $count = 0;

        AnnouncementTranslation::query()
            ->where('status', AnnouncementTranslationStatus::Completed)
            ->with('announcement')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$count): void {
                foreach ($rows as $row) {
                    $announcement = $row->announcement;
                    if ($announcement === null) {
                        continue;
                    }

                    $source = trim((string) $announcement->description);
                    $translated = trim((string) ($row->description ?? ''));
                    if (! TranslationOutputGuard::isUntranslatedEcho(
                        $translated,
                        $source,
                        (string) $row->locale,
                        $announcement->normalizedOriginalLanguage(),
                    )) {
                        continue;
                    }

                    $row->fill([
                        'description' => null,
                        'status' => AnnouncementTranslationStatus::Pending,
                    ])->save();
                    $count++;
                }
            });

        return $count;
    }

    private function requeueDocuments(): int
    {
        $count = 0;

        DocumentTranslation::query()
            ->where('status', DocumentTranslationStatus::Completed)
            ->with('document')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$count): void {
                foreach ($rows as $row) {
                    $document = $row->document;
                    if ($document === null) {
                        continue;
                    }

                    $source = trim((string) $document->description);
                    $translated = trim((string) ($row->description ?? ''));
                    if (! TranslationOutputGuard::isUntranslatedEcho(
                        $translated,
                        $source,
                        (string) $row->locale,
                        $document->normalizedOriginalLanguage(),
                    )) {
                        continue;
                    }

                    $row->fill([
                        'description' => null,
                        'status' => DocumentTranslationStatus::Pending,
                    ])->save();
                    $count++;
                }
            });

        return $count;
    }
}
