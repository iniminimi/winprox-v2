<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Communication\ExportPendingAnnouncementTranslationsAction;
use App\Actions\Communication\ExportPendingIssueTranslationsAction;
use App\Actions\Communication\ImportAnnouncementTranslationsAction;
use App\Actions\Communication\ImportIssueTranslationsAction;
use App\Actions\Communication\ReadTranslationSyncStatusAction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function export(
        ExportPendingIssueTranslationsAction $exportIssues,
        ExportPendingAnnouncementTranslationsAction $exportAnnouncements,
    ): JsonResponse {
        $this->authorize('runTranslationSync', User::class);

        $items = array_merge(
            $exportIssues->handle()['items'],
            $exportAnnouncements->handle(),
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
    ): JsonResponse {
        $this->authorize('runTranslationSync', User::class);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.locale' => ['required', 'string'],
            'items.*.description' => ['required', 'string'],
        ]);

        $items = $validated['items'];
        $actorUserId = (int) $request->user()?->id ?: null;

        $issueItems = [];
        $announcementItems = [];

        foreach ($items as $item) {
            if (isset($item['announcement_id'])) {
                $announcementItems[] = $item;
            } else {
                $issueItems[] = $item;
            }
        }

        $imported = $importIssues->handle($issueItems, $actorUserId)
            + $importAnnouncements->handle($announcementItems, $actorUserId);

        return $this->success(['imported' => $imported]);
    }

    public function status(ReadTranslationSyncStatusAction $readStatus): JsonResponse
    {
        $this->authorize('runTranslationSync', User::class);

        return $this->success(['status' => $readStatus->handle()]);
    }
}
