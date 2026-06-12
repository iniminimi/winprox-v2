<?php

namespace App\Console\Commands;

use App\Actions\Manual\ExecuteManualScreenshotCaptureAction;
use App\Actions\Manual\ReadManualScreenshotCaptureStatusAction;
use App\Actions\Manual\StartManualScreenshotCaptureAction;
use Illuminate\Console\Command;

class CaptureManualScreenshotsCommand extends Command
{
    protected $signature = 'winprox:manual-capture-screenshots {--sync : Voer direct uit i.p.v. queue}';

    protected $description = 'Genereer handleiding-screenshots (Playwright)';

    public function handle(
        StartManualScreenshotCaptureAction $start,
        ExecuteManualScreenshotCaptureAction $execute,
        ReadManualScreenshotCaptureStatusAction $readStatus,
    ): int {
        if ($this->option('sync')) {
            try {
                $result = $execute->handle();
                $this->info('Manual capture completed.');
                if ($result['output'] !== '') {
                    $this->line($result['output']);
                }

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                $this->printCaptureStatus($readStatus->handle());

                return self::FAILURE;
            }
        }

        try {
            $start->handle(0);
            $this->info('Manual capture queued.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>|null  $status
     */
    private function printCaptureStatus(?array $status): void
    {
        if ($status === null) {
            $this->line('Statusbestand: niet gevonden (storage/app/manual-capture/status.json)');

            return;
        }

        if (! empty($status['exit_code'])) {
            $this->line('Exit code: '.$status['exit_code']);
        }

        if (! empty($status['output'])) {
            $this->line($status['output']);
        }
    }
}
