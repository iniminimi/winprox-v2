<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Communication\ExportPendingAnnouncementTranslationsAction;
use App\Actions\Communication\BackfillCategoryTranslationSlotsAction;
use App\Actions\Communication\BackfillInternalTeamTranslationSlotsAction;
use App\Actions\Communication\ExportPendingCategoryTranslationsAction;
use App\Actions\Communication\ExportPendingDocumentTranslationsAction;
use App\Actions\Communication\ExportPendingInternalTeamTranslationsAction;
use App\Actions\Communication\ExportPendingIssueTranslationsAction;
use App\Actions\Communication\ExportPendingLocationTranslationsAction;
use App\Actions\Communication\ExportPendingTaskTranslationsAction;
use App\Actions\Communication\ExportPendingUnitTranslationsAction;
use App\Actions\Communication\ImportAnnouncementTranslationsAction;
use App\Actions\Communication\ImportCategoryTranslationsAction;
use App\Actions\Communication\ImportDocumentTranslationsAction;
use App\Actions\Communication\ImportInternalTeamTranslationsAction;
use App\Actions\Communication\ImportIssueTranslationsAction;
use App\Actions\Communication\ImportLocationTranslationsAction;
use App\Actions\Communication\ImportTaskTranslationsAction;
use App\Actions\Communication\ImportUnitTranslationsAction;
use App\Actions\Communication\ReadTranslationSyncStatusAction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function export(
        BackfillCategoryTranslationSlotsAction $backfillCategories,
        BackfillInternalTeamTranslationSlotsAction $backfillTeams,
        ExportPendingIssueTranslationsAction $exportIssues,
        ExportPendingAnnouncementTranslationsAction $exportAnnouncements,
        ExportPendingLocationTranslationsAction $exportLocations,
        ExportPendingUnitTranslationsAction $exportUnits,
        ExportPendingTaskTranslationsAction $exportTasks,
        ExportPendingDocumentTranslationsAction $exportDocuments,
        ExportPendingCategoryTranslationsAction $exportCategories,
        ExportPendingInternalTeamTranslationsAction $exportTeams,
    ): JsonResponse {
        $this->authorize('runTranslationSync', User::class);

        // Ensure legacy categories/teams receive translation slots before export.
        $backfillCategories->handle();
        $backfillTeams->handle();

        $items = array_merge(
            $exportIssues->handle()['items'],
            $exportAnnouncements->handle(),
            $exportLocations->handle(),
            $exportUnits->handle(),
            $exportTasks->handle(),
            $exportDocuments->handle(),
            $exportCategories->handle(),
            $exportTeams->handle(),
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
        ImportLocationTranslationsAction $importLocations,
        ImportUnitTranslationsAction $importUnits,
        ImportTaskTranslationsAction $importTasks,
        ImportDocumentTranslationsAction $importDocuments,
        ImportCategoryTranslationsAction $importCategories,
        ImportInternalTeamTranslationsAction $importTeams,
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
        $locationItems = [];
        $unitItems = [];
        $taskItems = [];
        $documentItems = [];
        $categoryItems = [];
        $teamItems = [];

        foreach ($items as $item) {
            if (isset($item['document_id'])) {
                $documentItems[] = $item;
            } elseif (isset($item['task_id'])) {
                $taskItems[] = $item;
            } elseif (isset($item['location_id'])) {
                $locationItems[] = $item;
            } elseif (isset($item['unit_id'])) {
                $unitItems[] = $item;
            } elseif (isset($item['announcement_id'])) {
                $announcementItems[] = $item;
            } elseif (isset($item['category_id'])) {
                $categoryItems[] = $item;
            } elseif (isset($item['internal_team_id'])) {
                $teamItems[] = $item;
            } else {
                $issueItems[] = $item;
            }
        }

        $imported = $importIssues->handle($issueItems, $actorUserId)
            + $importAnnouncements->handle($announcementItems, $actorUserId)
            + $importLocations->handle($locationItems, $actorUserId)
            + $importUnits->handle($unitItems, $actorUserId)
            + $importTasks->handle($taskItems, $actorUserId)
            + $importDocuments->handle($documentItems, $actorUserId)
            + $importCategories->handle($categoryItems, $actorUserId)
            + $importTeams->handle($teamItems, $actorUserId);

        return $this->success(['imported' => $imported]);
    }

    public function status(ReadTranslationSyncStatusAction $readStatus): JsonResponse
    {
        $this->authorize('runTranslationSync', User::class);

        return $this->success(['status' => $readStatus->handle()]);
    }
}
