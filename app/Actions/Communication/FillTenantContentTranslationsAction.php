<?php

declare(strict_types=1);

namespace App\Actions\Communication;

use App\Models\Announcement;
use App\Models\AnnouncementTranslation;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Document;
use App\Models\DocumentTranslation;
use App\Models\InternalTeam;
use App\Models\InternalTeamTranslation;
use App\Models\Issue;
use App\Models\IssueTranslation;
use App\Models\Location;
use App\Models\LocationTranslation;
use App\Models\Task;
use App\Models\TaskTranslation;
use App\Models\Unit;
use App\Models\UnitCheckList;
use App\Models\UnitCheckListTranslation;
use App\Models\UnitTranslation;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Facades\DB;

/**
 * Vult ontbrekende content-vertalingen voor één tenant (lokaal via Ollama + import-pad).
 * Locaties, categorieën en teams inbegrepen.
 */
class FillTenantContentTranslationsAction
{
    public function __construct(
        private BackfillIssueTranslationSlotsAction $backfillIssues,
        private BackfillAnnouncementTranslationSlotsAction $backfillAnnouncements,
        private BackfillLocationTranslationSlotsAction $backfillLocations,
        private BackfillUnitTranslationSlotsAction $backfillUnits,
        private BackfillTaskTranslationSlotsAction $backfillTasks,
        private BackfillDocumentTranslationSlotsAction $backfillDocuments,
        private BackfillEsgIndicatorTranslationSlotsAction $backfillEsgIndicators,
        private BackfillCategoryTranslationSlotsAction $backfillCategories,
        private BackfillInternalTeamTranslationSlotsAction $backfillTeams,
        private BackfillUnitCheckListTranslationSlotsAction $backfillUnitCheckLists,
        private TranslateExportItemsAction $translateItems,
        private ImportIssueTranslationsAction $importIssues,
        private ImportAnnouncementTranslationsAction $importAnnouncements,
        private ImportLocationTranslationsAction $importLocations,
        private ImportUnitTranslationsAction $importUnits,
        private ImportTaskTranslationsAction $importTasks,
        private ImportDocumentTranslationsAction $importDocuments,
        private ImportEsgIndicatorTranslationsAction $importEsgIndicators,
        private ImportCategoryTranslationsAction $importCategories,
        private ImportInternalTeamTranslationsAction $importTeams,
        private ImportUnitCheckListTranslationsAction $importUnitCheckLists,
        private ExportPendingIssueTranslationsAction $exportIssues,
        private ExportPendingAnnouncementTranslationsAction $exportAnnouncements,
        private ExportPendingLocationTranslationsAction $exportLocations,
        private ExportPendingUnitTranslationsAction $exportUnits,
        private ExportPendingTaskTranslationsAction $exportTasks,
        private ExportPendingDocumentTranslationsAction $exportDocuments,
        private ExportPendingEsgIndicatorTranslationsAction $exportEsgIndicators,
        private ExportPendingCategoryTranslationsAction $exportCategories,
        private ExportPendingInternalTeamTranslationsAction $exportTeams,
        private ExportPendingUnitCheckListTranslationsAction $exportUnitCheckLists,
    ) {}

    /**
     * @return array{normalized: int, slots: array<string, mixed>, pending: int, imported: int}
     */
    public function handle(int $tenantId, ?int $actorUserId = null, ?callable $onProgress = null): array
    {
        $normalized = $this->normalizeEnglishSources($tenantId);

        $slots = [
            'issues' => $this->backfillIssues->handle($tenantId),
            'announcements' => $this->backfillAnnouncements->handle($tenantId),
            'locations' => $this->backfillLocations->handle($tenantId),
            'units' => $this->backfillUnits->handle($tenantId),
            'tasks' => $this->backfillTasks->handle($tenantId),
            'documents' => $this->backfillDocuments->handle($tenantId),
            'esg' => $this->backfillEsgIndicators->handle($tenantId),
            'categories' => $this->backfillCategories->handle($tenantId),
            'teams' => $this->backfillTeams->handle($tenantId),
            'unit_check_lists' => $this->backfillUnitCheckLists->handle($tenantId),
        ];

        $pending = [];
        foreach ([
            $this->exportIssues->handle()['items'] ?? [],
            $this->exportAnnouncements->handle(),
            $this->exportLocations->handle(),
            $this->exportUnits->handle(),
            $this->exportTasks->handle(),
            $this->exportDocuments->handle(),
            $this->exportEsgIndicators->handle(),
            $this->exportCategories->handle(),
            $this->exportTeams->handle(),
            $this->exportUnitCheckLists->handle(),
        ] as $batch) {
            if (! is_array($batch)) {
                continue;
            }
            foreach ($batch as $item) {
                if (! is_array($item)) {
                    continue;
                }
                if ((int) ($item['tenant_id'] ?? 0) !== $tenantId) {
                    continue;
                }
                $pending[] = $item;
            }
        }

        $translated = $this->translateItems->handle($pending, $onProgress);

        $imported = $this->importIssues->handle(
            array_values(array_filter($translated, static fn (array $i): bool => isset($i['issue_id']))),
            $actorUserId,
        )
            + $this->importAnnouncements->handle(
                array_values(array_filter($translated, static fn (array $i): bool => isset($i['announcement_id']))),
                $actorUserId,
            )
            + $this->importLocations->handle(
                array_values(array_filter($translated, static fn (array $i): bool => isset($i['location_id']))),
                $actorUserId,
            )
            + $this->importUnits->handle(
                array_values(array_filter($translated, static fn (array $i): bool => isset($i['unit_id']))),
                $actorUserId,
            )
            + $this->importTasks->handle(
                array_values(array_filter($translated, static fn (array $i): bool => isset($i['task_id']))),
                $actorUserId,
            )
            + $this->importDocuments->handle(
                array_values(array_filter($translated, static fn (array $i): bool => isset($i['document_id']))),
                $actorUserId,
            )
            + $this->importEsgIndicators->handle(
                array_values(array_filter($translated, static fn (array $i): bool => isset($i['esg_indicator_id']))),
                $actorUserId,
            )
            + $this->importCategories->handle(
                array_values(array_filter($translated, static fn (array $i): bool => isset($i['category_id']))),
                $actorUserId,
            )
            + $this->importTeams->handle(
                array_values(array_filter($translated, static fn (array $i): bool => isset($i['internal_team_id']))),
                $actorUserId,
            )
            + $this->importUnitCheckLists->handle(
                array_values(array_filter($translated, static fn (array $i): bool => isset($i['unit_check_list_id']))),
                $actorUserId,
            );

        return [
            'normalized' => $normalized,
            'slots' => $slots,
            'pending' => count($pending),
            'imported' => $imported,
        ];
    }

    /**
     * Engels ogende bronnen als `en` markeren zodat NL een echte vertaalslot krijgt (beter voor screenshots).
     */
    private function normalizeEnglishSources(int $tenantId): int
    {
        $changed = 0;

        $changed += $this->repointSourceLanguage(
            Issue::query()->where('tenant_id', $tenantId)->whereNotNull('approved_at')->get(),
            IssueTranslation::class,
            'issue_id',
            static fn (Issue $issue): string => trim((string) $issue->description),
        );

        $changed += $this->repointSourceLanguage(
            Task::query()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('description')
                ->where('description', '!=', '')
                ->get(),
            TaskTranslation::class,
            'task_id',
            static fn (Task $task): string => trim((string) $task->description),
        );

        $changed += $this->repointSourceLanguage(
            Announcement::query()->where('tenant_id', $tenantId)->where('is_active', true)->get(),
            AnnouncementTranslation::class,
            'announcement_id',
            static fn (Announcement $row): string => trim((string) $row->description),
        );

        $changed += $this->repointSourceLanguage(
            Document::query()->where('tenant_id', $tenantId)->where('is_active', true)->get(),
            DocumentTranslation::class,
            'document_id',
            static fn (Document $row): string => trim((string) ($row->description ?? '')),
        );

        $changed += $this->repointSourceLanguage(
            Location::query()->where('tenant_id', $tenantId)->where('is_active', true)->get(),
            LocationTranslation::class,
            'location_id',
            static fn (Location $row): string => trim((string) $row->name),
        );

        $changed += $this->repointSourceLanguage(
            Category::query()->where('tenant_id', $tenantId)->get(),
            CategoryTranslation::class,
            'category_id',
            static fn (Category $row): string => trim((string) $row->name),
        );

        $changed += $this->repointSourceLanguage(
            InternalTeam::query()->where('tenant_id', $tenantId)->where('is_active', true)->get(),
            InternalTeamTranslation::class,
            'internal_team_id',
            static fn (InternalTeam $row): string => trim((string) $row->name),
        );

        $changed += $this->repointSourceLanguage(
            UnitCheckList::query()->where('tenant_id', $tenantId)->where('is_active', true)->get(),
            UnitCheckListTranslation::class,
            'unit_check_list_id',
            static fn (UnitCheckList $row): string => trim((string) $row->name),
        );

        // Units: alleen herlabelen als naam+omschrijving geen duidelijk Nederlands bevatten.
        foreach (Unit::query()->where('tenant_id', $tenantId)->where('is_active', true)->get() as $unit) {
            $blob = trim($unit->name.' '.((string) ($unit->description ?? '')));
            if ($blob === '' || ! $this->looksEnglish($blob) || $this->looksDutch($blob)) {
                continue;
            }

            $changed += $this->repointOne($unit, UnitTranslation::class, 'unit_id', 'en');
        }

        return $changed;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $parents
     * @param  class-string  $translationClass
     * @param  callable(object): string  $sourceText
     */
    private function repointSourceLanguage($parents, string $translationClass, string $fk, callable $sourceText): int
    {
        $changed = 0;

        foreach ($parents as $parent) {
            $text = $sourceText($parent);
            if ($text === '' || ! $this->looksEnglish($text) || $this->looksDutch($text)) {
                continue;
            }

            $changed += $this->repointOne($parent, $translationClass, $fk, 'en');
        }

        return $changed;
    }

    /**
     * @param  class-string  $translationClass
     */
    private function repointOne(object $parent, string $translationClass, string $fk, string $newSource): int
    {
        $newSource = LocaleSupport::normalize($newSource);
        $current = LocaleSupport::normalize((string) ($parent->original_language ?? null));

        if ($current === $newSource) {
            return 0;
        }

        DB::transaction(function () use ($parent, $translationClass, $fk, $newSource): void {
            $parent->forceFill(['original_language' => $newSource])->save();

            $translationClass::query()
                ->where($fk, $parent->id)
                ->where('locale', $newSource)
                ->delete();

            // Oude completed slots voor andere talen blijven; ontbrekende slots via backfill.
            // Pending/failed voor de nieuwe brontaal bestaan niet meer na delete hierboven.
        });

        return 1;
    }

    private function looksEnglish(string $text): bool
    {
        $lower = mb_strtolower($text);

        return (bool) preg_match(
            '/\b(the|and|please|printer|help|manual|dirty|clean|check|fail|work|user|call|new|unavailable|sports|hall|does|not|properly|saying)\b/u',
            $lower,
        );
    }

    private function looksDutch(string $text): bool
    {
        $lower = mb_strtolower($text);

        return (bool) preg_match(
            '/\b(deze|staat|aan|het|venster|printer|niet|werkt|graag|controleer|schoonmaken)\b/u',
            $lower,
        ) && (bool) preg_match('/\b(deze|staat|venster|graag|controleer|schoonmaken)\b/u', $lower);
    }
}
