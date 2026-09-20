<?php

namespace App\Console\Commands;

use App\Actions\Retention\PruneClosedIssueMediaAction;
use App\Actions\Retention\PruneInactiveTenantFacilityDataAction;
use App\Actions\Retention\PrunePresenceSubmissionsAction;
use Illuminate\Console\Command;

class RetentionPruneCommand extends Command
{
    protected $signature = 'winprox:retention-prune {--dry-run : Toon wat verwijderd zou worden zonder te wijzigen}';

    protected $description = 'Prune closed issue photos, CIAO submissions, and inactive tenant facility data per retention policy';

    public function handle(
        PruneClosedIssueMediaAction $pruneMedia,
        PrunePresenceSubmissionsAction $prunePresence,
        PruneInactiveTenantFacilityDataAction $pruneTenants,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry-run: geen bestanden of records worden verwijderd.');
        }

        $media = $pruneMedia->handle($dryRun);
        $this->info(sprintf(
            'Closed issue media: %d meldingen, %d foto\'s %s',
            $media['issues_scanned'],
            $media['photos_removed'],
            $dryRun ? '(zou verwijderen)' : 'verwijderd',
        ));

        $presence = $prunePresence->handle($dryRun);
        $this->info(sprintf(
            'CIAO submissions: %d gescand, %d %s',
            $presence['scanned'],
            $presence['removed'],
            $dryRun ? '(zou verwijderen)' : 'verwijderd',
        ));

        $tenants = $pruneTenants->handle($dryRun);
        $this->info(sprintf(
            'Inactive tenants: %d tenants, %d meldingen, %d foto\'s %s',
            $tenants['tenants_scanned'],
            $tenants['issues_removed'],
            $tenants['photos_removed'],
            $dryRun ? '(zou verwijderen)' : 'verwijderd',
        ));

        return self::SUCCESS;
    }
}
