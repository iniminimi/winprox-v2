<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Dev\SeedTenantDemoDataAction;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SeedTenantDemoDataCommand extends Command
{
    protected $signature = 'winprox:seed-tenant-demo
        {tenant=3 : Tenant-ID}
        {--clock-points=10 : Streefaantal clock points}
        {--no-esg : Sla ESG-data over}
        {--no-time : Sla Time-data over}
        {--force : Ook buiten local uitvoeren}';

    protected $description = 'Seed demo Clock Point-, ESG- en Time-data voor lokale tests';

    public function handle(SeedTenantDemoDataAction $seed): int
    {
        if (! app()->environment('local') && ! $this->option('force')) {
            $this->error('Alleen lokaal. Gebruik --force om toch te seeden.');

            return self::FAILURE;
        }

        $tenantId = (int) $this->argument('tenant');
        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            $this->error("Tenant #{$tenantId} niet gevonden.");

            return self::FAILURE;
        }

        $result = $seed->handle($tenant, [
            'clock_points' => (int) $this->option('clock-points'),
            'esg' => ! $this->option('no-esg'),
            'time' => ! $this->option('no-time'),
        ]);

        $this->info("Tenant #{$tenant->id} ({$tenant->name}) — demo-data toegevoegd:");
        $this->line("  Clock points: +{$result['clock_points_created']} (totaal {$result['clock_points_total']})");
        $this->line("  ESG: {$result['esg_indicators']} indicatoren, {$result['esg_measurements']} metingen");
        $this->line("  Time: {$result['work_shifts']} shifts ({$result['workers_total']} actieve workers)");

        return self::SUCCESS;
    }
}
