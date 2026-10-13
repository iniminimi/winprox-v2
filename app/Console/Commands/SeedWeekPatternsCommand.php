<?php

namespace App\Console\Commands;

use App\Actions\Team\BackfillWorkerWeekPatternsAction;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SeedWeekPatternsCommand extends Command
{
    protected $signature = 'workers:seed-week-pattern
                            {--tenant= : Tenant-id (leeg = alle tenants met de Time-module)}
                            {--dry-run : Toon wat er zou gebeuren zonder op te slaan}';

    protected $description = 'Geef uitvoerders zonder weekpatroon de standaard ma–vr-werkregeling';

    public function handle(BackfillWorkerWeekPatternsAction $backfill): int
    {
        $tenantId = $this->option('tenant');
        $tenants = Tenant::query()
            ->when($tenantId !== null, fn ($query) => $query->where('id', (int) $tenantId))
            ->where('has_time_module', true)
            ->orderBy('id')
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('Geen tenants met de Time-module gevonden.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->info('DRY RUN — er wordt niets opgeslagen.');
        }

        foreach ($tenants as $tenant) {
            $result = $backfill->handle($tenant, $dryRun);

            $this->line("Tenant {$tenant->id} — {$tenant->name}");
            $this->line("  uitvoerders:            {$result['workers']}");
            $this->line("  al een volledig patroon: {$result['already_patterned']}");
            $this->line("  aangevuld (38u):         {$result['seeded']}");
            $this->line("  aangevuld (afwijkend):   {$result['seeded_deviating']}");
            $this->line("  contractnorm aangevuld:  {$result['contract_filled']}");
            $this->line("  onvolledig patroon:      {$result['incomplete_patterns']}");

            foreach ($result['deviating_workers'] as $worker) {
                $this->line("    · #{$worker['id']} {$worker['name']} — {$worker['contract_minutes']} min/week");
            }
            foreach ($result['incomplete_workers'] as $worker) {
                $this->line("    ! #{$worker['id']} {$worker['name']} — {$worker['rows']} patroonrijen (ongewijzigd)");
            }
        }

        $this->info($dryRun ? 'Controleer de tellingen en voer opnieuw uit zonder --dry-run.' : 'Klaar.');

        return self::SUCCESS;
    }
}
