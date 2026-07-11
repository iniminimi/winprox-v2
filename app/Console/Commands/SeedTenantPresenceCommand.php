<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Dev\SeedTenantPresenceAction;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SeedTenantPresenceCommand extends Command
{
    protected $signature = 'winprox:seed-presence
        {tenant=3 : Tenant-ID}
        {--open=320 : Totaal aantal open shifts (ingelogd)}
        {--on-break=45 : Aantal daarvan op pauze}
        {--force : Ook buiten local uitvoeren}';

    protected $description = 'Seed open shifts voor aanwezigheidstests (lokaal)';

    public function handle(SeedTenantPresenceAction $seed): int
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

        $openTarget = max(0, (int) $this->option('open'));
        $onBreakTarget = max(0, min((int) $this->option('on-break'), $openTarget));

        $result = $seed->handle($tenant, $openTarget, $onBreakTarget);

        $this->info("Tenant #{$tenant->id} ({$tenant->name}) — aanwezigheid:");
        $this->line("  Ingelogd: {$result['open_shifts']} (doel {$openTarget})");
        $this->line("  Aanwezig: {$result['present']}");
        $this->line("  Pauze: {$result['on_break']} (doel {$onBreakTarget})");
        $this->line("  Actieve workers: {$result['workers_total']}");

        return self::SUCCESS;
    }
}
