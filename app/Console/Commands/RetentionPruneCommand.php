<?php

namespace App\Console\Commands;

use App\Actions\Retention\ArchiveDueTenantTimeYearsAction;
use App\Actions\Retention\PruneAbsenceRequestPhotosAction;
use App\Actions\Retention\PruneCameraPhotosAction;
use App\Actions\Retention\PruneClosedIssueMediaAction;
use App\Actions\Retention\PruneExpiredTimeArchivesAction;
use App\Actions\Retention\PruneInactiveTenantFacilityDataAction;
use App\Actions\Retention\PrunePresenceSubmissionsAction;
use App\Actions\Retention\PruneRosterRestorePointsAction;
use App\Actions\Retention\PruneUnitAlbumPhotosAction;
use Illuminate\Console\Command;

class RetentionPruneCommand extends Command
{
    protected $signature = 'winprox:retention-prune {--dry-run : Toon wat verwijderd zou worden zonder te wijzigen}';

    protected $description = 'Prune closed issue photos, CIAO submissions, roster restore points, unit album photos, and inactive tenant facility data per retention policy';

    public function handle(
        PruneClosedIssueMediaAction $pruneMedia,
        PrunePresenceSubmissionsAction $prunePresence,
        PruneInactiveTenantFacilityDataAction $pruneTenants,
        PruneRosterRestorePointsAction $pruneRosterRestorePoints,
        PruneUnitAlbumPhotosAction $pruneUnitAlbumPhotos,
        PruneAbsenceRequestPhotosAction $pruneAbsencePhotos,
        PruneCameraPhotosAction $pruneCameraPhotos,
        ArchiveDueTenantTimeYearsAction $archiveTimeYears,
        PruneExpiredTimeArchivesAction $pruneTimeArchives,
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

        $restorePoints = $pruneRosterRestorePoints->handle($dryRun);
        $this->info(sprintf(
            'Herstelpunten: %d gescand, %d %s',
            $restorePoints['scanned'],
            $restorePoints['removed'],
            $dryRun ? '(zou verwijderen)' : 'verwijderd',
        ));

        $absencePhotos = $pruneAbsencePhotos->handle($dryRun);
        $this->info(sprintf(
            'Afwezigheidsfoto\'s: %d gescand, %d %s',
            $absencePhotos['scanned'],
            $absencePhotos['removed'],
            $dryRun ? '(zou verwijderen)' : 'verwijderd',
        ));

        $cameraPhotos = $pruneCameraPhotos->handle($dryRun);
        $this->info(sprintf(
            'Camerafoto\'s: %d gescand, %d %s',
            $cameraPhotos['scanned'],
            $cameraPhotos['removed'],
            $dryRun ? '(zou verwijderen)' : 'verwijderd',
        ));

        $albumPhotos = $pruneUnitAlbumPhotos->handle($dryRun);
        $this->info(sprintf(
            'Fotoalbum: %d gescand, %d %s',
            $albumPhotos['scanned'],
            $albumPhotos['removed'],
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

        $years = $archiveTimeYears->handle($dryRun);
        $this->info(sprintf(
            'Tijdarchief: %d tenants, %d jaren, %d rijen %s, %d overgeslagen, %d fouten',
            $years['tenants'],
            $years['years'],
            $years['deleted'],
            $dryRun ? '(zou archiveren)' : 'gearchiveerd',
            $years['skipped'],
            $years['errors'],
        ));

        $archives = $pruneTimeArchives->handle($dryRun);
        $this->info(sprintf(
            'Verlopen tijdarchieven: %d gescand, %d %s',
            $archives['scanned'],
            $archives['removed'],
            $dryRun ? '(zou verwijderen)' : 'verwijderd',
        ));

        return $years['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
