<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Communication\ExportPendingAnnouncementTranslationsAction;
use App\Actions\Communication\ExportPendingIssueTranslationsAction;
use App\Actions\Communication\ExportPendingTaskTranslationsAction;
use App\Actions\Communication\ExportPendingUnitTranslationsAction;
use App\Actions\Communication\ImportAnnouncementTranslationsAction;
use App\Actions\Communication\ImportIssueTranslationsAction;
use App\Actions\Communication\ImportTaskTranslationsAction;
use App\Actions\Communication\ImportUnitTranslationsAction;
use App\Actions\Communication\ReadTranslationSyncStatusAction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function export(
        ExportPendingIssueTranslationsAction $exportIssues,
        ExportPendingAnnouncementTranslationsAction $exportAnnouncements,
        ExportPendingUnitTranslationsAction $exportUnits,
        ExportPendingTaskTranslationsAction $exportTasks,
    ): JsonResponse {
        $this->authorize('runTranslationSync', User::class);

        $items = array_merge(
            $exportIssues->handle()['items'],
            $exportAnnouncements->handle(),
            $exportUnits->handle(),
            $exportTasks->handle(),
        );

        return $this->success([
            'exported_at' => now()->toIso8601String(),
            'count' => count($items),
            'items' => $items,
        ]);
    }

    public function import(
        Request $request,
        ImportIssueTranslationsAction $importIssues,
        ImportAnnouncementTranslationsAction $importAnnouncements,
        ImportUnitTranslationsAction $importUnits,
        ImportTaskTranslationsAction $importTasks,
    ): JsonResponse {
        $this->authorize('runTranslationSync', User::class);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.locale' => ['required', 'string'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.name' => ['nullable', 'string'],
        ]);

        $items = $validated['items'];
        $actorUserId = (int) $request->user()?->id ?: null;

        $issueItems = [];
        $announcementItems = [];
        $unitItems = [];
        $taskItems = [];

        foreach ($items as $item) {
            if (isset($item['task_id'])) {
                $taskItems[] = $item;
            } elseif (isset($item['unit_id'])) {
                $unitItems[] = $item;
            } elseif (isset($item['announcement_id'])) {
                $announcementItems[] = $item;
            } else {
                $issueItems[] = $item;
            }
        }

        $imported = $importIssues->handle($issueItems, $actorUserId)
            + $importAnnouncements->handle($announcementItems, $actorUserId)
            + $importUnits->handle($unitItems, $actorUserId)
            + $importTasks->handle($taskItems, $actorUserId);

        return $this->success(['imported' => $imported]);
    }

    public function status(ReadTranslationSyncStatusAction $readStatus): JsonResponse
    {
        $this->authorize('runTranslationSync', User::class);

        return $this->success(['status' => $readStatus->handle()]);
    }
}
