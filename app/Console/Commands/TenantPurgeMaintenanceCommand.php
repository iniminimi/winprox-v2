<?php

namespace App\Console\Commands;

use App\Actions\TenantPurge\PruneExpiredTenantPurgeBackupsAction;
use App\Actions\TenantPurge\SendTenantPurgeRemindersAction;
use Illuminate\Console\Command;

class TenantPurgeMaintenanceCommand extends Command
{
    protected $signature = 'winprox:tenant-purge-maintenance
                            {--dry-run : Alleen backup-prune tellen, niets wissen}';

    protected $description = 'Stuur T−2 purge-reminders en ruim verlopen tenant-purge backups op';

    public function handle(
        SendTenantPurgeRemindersAction $reminders,
        PruneExpiredTenantPurgeBackupsAction $pruneBackups,
    ): int {
        $reminderStats = $reminders->handle();
        $this->info(sprintf(
            'Reminders: scanned=%d sent=%d',
            $reminderStats['scanned'],
            $reminderStats['sent'],
        ));

        $pruneStats = $pruneBackups->handle(dryRun: (bool) $this->option('dry-run'));
        $this->info(sprintf(
            'Backup prune%s: scanned=%d deleted=%d',
            $this->option('dry-run') ? ' (dry-run)' : '',
            $pruneStats['scanned'],
            $pruneStats['deleted'],
        ));

        return self::SUCCESS;
    }
}
