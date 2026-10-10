<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Locations\EnsureSiteUnitsForEmptyLocationsAction;
use App\Models\Tenant;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Backfill «Hele locatie»-units voor locaties zonder units
 * (o.a. legacy Checkmate-werkadressen zonder site-unit).
 */
class EnsureSiteUnitsCommand extends Command
{
    protected $signature = 'winprox:ensure-site-units
                            {--tenant= : Alleen deze tenant-id}
                            {--checkmate-only : Alleen Checkmate-tenants}';

    protected $description = 'Maak Hele-locatie-units voor locaties zonder units';

    public function handle(EnsureSiteUnitsForEmptyLocationsAction $ensure): int
    {
        $tenantId = $this->option('tenant');
        $checkmateOnly = (bool) $this->option('checkmate-only');

        $query = Tenant::query()->orderBy('id');
        if ($tenantId !== null && $tenantId !== '') {
            $query->whereKey((int) $tenantId);
        }
        if ($checkmateOnly) {
            $query->where('checkmate_mode', true);
        }

        $tenants = $query->get();
        if ($tenants->isEmpty()) {
            $this->warn('Geen tenants gevonden.');

            return self::SUCCESS;
        }

        $totalCreated = 0;
        foreach ($tenants as $tenant) {
            try {
                $result = $ensure->handle((int) $tenant->id, null);
            } catch (InvalidArgumentException $e) {
                $this->error("Tenant #{$tenant->id}: ".$e->getMessage());

                continue;
            }

            if ($result['created'] > 0 || $result['empty_count'] > 0) {
                $this->line(sprintf(
                    'Tenant #%d (%s): created=%d skipped=%d empty=%d',
                    $tenant->id,
                    $tenant->name,
                    $result['created'],
                    $result['skipped'],
                    $result['empty_count'],
                ));
            }
            $totalCreated += $result['created'];
        }

        $this->info("Klaar. {$totalCreated} site-unit(s) aangemaakt.");

        return self::SUCCESS;
    }
}
