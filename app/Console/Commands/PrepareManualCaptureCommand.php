<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Manual\PrepareManualCaptureTenantAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

class PrepareManualCaptureCommand extends Command
{
    protected $signature = 'winprox:prepare-manual-capture';

    protected $description = 'Bereid de MANUAL_CAPTURE_EMAIL-tenant voor (trial/toegang, ESG, Time, IoT, Clock Point)';

    public function handle(PrepareManualCaptureTenantAction $prepare): int
    {
        try {
            $tenant = $prepare->handle();
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Capture-tenant #{$tenant->id} ({$tenant->name}): app-toegang + ESG/Time/IoT klaar.");

        $clockPointToken = $prepare->clockPointQrToken($tenant);
        if (is_string($clockPointToken) && $clockPointToken !== '') {
            $this->line("Clock Point QR-token: {$clockPointToken}");
            $this->line('MANUAL_CAPTURE_CLOCK_POINT_TOKEN='.$clockPointToken);
        }

        $checkmateEmail = trim((string) config('manual_capture.checkmate_email'));
        if ($checkmateEmail !== '') {
            try {
                $checkmateTenant = $prepare->handle($checkmateEmail, checkmate: true);
            } catch (InvalidArgumentException $e) {
                $this->warn("Checkmate-capture overgeslagen: {$e->getMessage()}");

                return self::SUCCESS;
            }

            $this->info("Checkmate-tenant #{$checkmateTenant->id} ({$checkmateTenant->name}): checkmate_mode + Time + GPS-bezoeken klaar.");

            $checkmateClockPointToken = $prepare->clockPointQrToken($checkmateTenant);
            if (is_string($checkmateClockPointToken) && $checkmateClockPointToken !== '') {
                $this->line('MANUAL_CAPTURE_CHECKMATE_CLOCK_POINT_TOKEN='.$checkmateClockPointToken);
            }
        }

        return self::SUCCESS;
    }
}
