<?php

namespace App\Console\Commands;

use App\Actions\Manual\ExecuteManualScreenshotCaptureAction;
use App\Actions\Manual\StartManualScreenshotCaptureAction;
use Illuminate\Console\Command;

class CaptureManualScreenshotsCommand extends Command
{
    protected $signature = 'winprox:manual-capture-screenshots {--sync : Voer direct uit i.p.v. queue}';

    protected $description = 'Genereer handleiding-screenshots (Playwright)';

    public function handle(
        StartManualScreenshotCaptureAction $start,
        ExecuteManualScreenshotCaptureAction $execute,
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
}
