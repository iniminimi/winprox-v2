<?php

namespace App\Console\Commands;

use App\Actions\Communication\BackfillAnnouncementTranslationSlotsAction;
use App\Actions\Communication\BackfillIssueTranslationSlotsAction;
use Illuminate\Console\Command;

class TranslationBackfillSlotsCommand extends Command
{
    protected $signature = 'translation:backfill-slots {--tenant= : Alleen voor deze tenant-id}';

    protected $description = 'Maak ontbrekende vertaal-slots voor goedgekeurde meldingen en actieve mededelingen';

    public function handle(
        BackfillIssueTranslationSlotsAction $backfillIssues,
        BackfillAnnouncementTranslationSlotsAction $backfillAnnouncements,
    ): int {
        $tenantId = $this->option('tenant');
        $tenantFilter = $tenantId !== null && $tenantId !== '' ? (int) $tenantId : null;

        $issues = $backfillIssues->handle($tenantFilter);
        $announcements = $backfillAnnouncements->handle($tenantFilter);

        $this->info(
            "Verwerkt: {$issues['issues']} melding(en), {$issues['slots_created']} melding-slot(s); "
            ."{$announcements['announcements']} mededeling(en), {$announcements['slots_created']} mededeling-slot(s)."
        );

        return self::SUCCESS;
    }
}
