<?php

namespace App\Console\Commands;

use App\Actions\Communication\BackfillIssueTranslationSlotsAction;
use Illuminate\Console\Command;

class TranslationBackfillSlotsCommand extends Command
{
    protected $signature = 'translation:backfill-slots {--tenant= : Alleen voor deze tenant-id}';

    protected $description = 'Maak ontbrekende vertaal-slots voor goedgekeurde meldingen';

    public function handle(BackfillIssueTranslationSlotsAction $backfill): int
    {
        $tenantId = $this->option('tenant');
        $tenantFilter = $tenantId !== null && $tenantId !== '' ? (int) $tenantId : null;

        $result = $backfill->handle($tenantFilter);

        $this->info("Verwerkt: {$result['issues']} melding(en), {$result['slots_created']} nieuwe slot(s).");

        return self::SUCCESS;
    }
}
