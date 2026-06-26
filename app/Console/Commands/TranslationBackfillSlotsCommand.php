<?php

namespace App\Console\Commands;

use App\Actions\Communication\BackfillAnnouncementTranslationSlotsAction;
use App\Actions\Communication\BackfillIssueTranslationSlotsAction;
use App\Actions\Communication\BackfillDocumentTranslationSlotsAction;
use App\Actions\Communication\BackfillLocationTranslationSlotsAction;
use App\Actions\Communication\BackfillTaskTranslationSlotsAction;
use App\Actions\Communication\BackfillUnitTranslationSlotsAction;
use Illuminate\Console\Command;

class TranslationBackfillSlotsCommand extends Command
{
    protected $signature = 'translation:backfill-slots {--tenant= : Alleen voor deze tenant-id}';

    protected $description = 'Maak ontbrekende vertaal-slots voor goedgekeurde meldingen, actieve mededelingen, actieve locaties, actieve units, taken met omschrijving en actieve documenten';

    public function handle(
        BackfillIssueTranslationSlotsAction $backfillIssues,
        BackfillAnnouncementTranslationSlotsAction $backfillAnnouncements,
        BackfillLocationTranslationSlotsAction $backfillLocations,
        BackfillUnitTranslationSlotsAction $backfillUnits,
        BackfillTaskTranslationSlotsAction $backfillTasks,
        BackfillDocumentTranslationSlotsAction $backfillDocuments,
    ): int {
        $tenantId = $this->option('tenant');
        $tenantFilter = $tenantId !== null && $tenantId !== '' ? (int) $tenantId : null;

        $issues = $backfillIssues->handle($tenantFilter);
        $announcements = $backfillAnnouncements->handle($tenantFilter);
        $locations = $backfillLocations->handle($tenantFilter);
        $units = $backfillUnits->handle($tenantFilter);
        $tasks = $backfillTasks->handle($tenantFilter);
        $documents = $backfillDocuments->handle($tenantFilter);

        $this->info(
            "Verwerkt: {$issues['issues']} melding(en), {$issues['slots_created']} melding-slot(s); "
            ."{$announcements['announcements']} mededeling(en), {$announcements['slots_created']} mededeling-slot(s); "
            ."{$locations['locations']} locatie(s), {$locations['slots_created']} locatie-slot(s); "
            ."{$units['units']} unit(s), {$units['slots_created']} unit-slot(s); "
            ."{$tasks['tasks']} taak/taken, {$tasks['slots_created']} taak-slot(s); "
            ."{$documents['documents']} document(en), {$documents['slots_created']} document-slot(s)."
        );

        return self::SUCCESS;
    }
}
