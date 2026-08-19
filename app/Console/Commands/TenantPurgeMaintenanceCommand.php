<?php

namespace App\Console\Commands;

use App\Actions\TenantPurge\ExecuteDueExpiredTrialPurgesAction;
use App\Actions\TenantPurge\PruneExpiredTenantPurgeBackupsAction;
use App\Actions\TenantPurge\PurgeUnverifiedTenantRegistrationsAction;
use App\Actions\TenantPurge\ScheduleExpiredTrialPurgesAction;
use App\Actions\TenantPurge\SendTenantPurgeRemindersAction;
use Illuminate\Console\Command;

class TenantPurgeMaintenanceCommand extends Command
{
    protected $signature = 'winprox:tenant-purge-maintenance
                            {--dry-run : Alleen backup-prune tellen, niets wissen}';

    protected $description = 'Wis niet-geverifieerde registraties, plan expired-trial purges, stuur reminders, voer due auto-purges uit, ruim backups op';

    public function handle(
        PurgeUnverifiedTenantRegistrationsAction $purgeUnverified,
        ScheduleExpiredTrialPurgesAction $scheduleExpired,
        SendTenantPurgeRemindersAction $reminders,
        ExecuteDueExpiredTrialPurgesAction $executeExpired,
        PruneExpiredTenantPurgeBackupsAction $pruneBackups,
    ): int {
        $unverifiedStats = $purgeUnverified->handle();
        $this->info(sprintf(
            'Unverified registrations: scanned=%d deleted=%d',
            $unverifiedStats['scanned'],
            $unverifiedStats['deleted'],
        ));

        $scheduleStats = $scheduleExpired->handle();
        $this->info(sprintf(
            'Expired-trial schedule: scanned=%d scheduled=%d',
            $scheduleStats['scanned'],
            $scheduleStats['scheduled'],
        ));

        $reminderStats = $reminders->handle();
        $this->info(sprintf(
            'Reminders: scanned=%d sent=%d',
            $reminderStats['scanned'],
            $reminderStats['sent'],
        ));

        $executeStats = $executeExpired->handle();
        $this->info(sprintf(
            'Expired-trial execute: scanned=%d executed=%d failed=%d',
            $executeStats['scanned'],
            $executeStats['executed'],
            $executeStats['failed'],
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
