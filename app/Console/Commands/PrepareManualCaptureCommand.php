<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Manual\PrepareManualCaptureTenantAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

class PrepareManualCaptureCommand extends Command
{
    protected $signature = 'winprox:prepare-manual-capture';

    protected $description = 'Zet has_esg_module aan voor de MANUAL_CAPTURE_EMAIL-tenant (handleiding-screenshots)';

    public function handle(PrepareManualCaptureTenantAction $prepare): int
    {
        try {
            $tenant = $prepare->handle();
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Capture-tenant #{$tenant->id} ({$tenant->name}): ESG-module actief.");

        return self::SUCCESS;
    }
}
