<?php

namespace App\Console\Commands;

use App\Actions\Public\ExpireQrReportEmailHoldsAction;
use Illuminate\Console\Command;

class ExpireQrReportEmailHoldsCommand extends Command
{
    protected $signature = 'winprox:qr-report-email-holds-expire';

    protected $description = 'Delete expired unverified QR report email holds and prune old confirmed rows';

    public function handle(ExpireQrReportEmailHoldsAction $expire): int
    {
        $count = $expire->handle();
        $this->info("Expired QR report email holds: {$count}");

        return self::SUCCESS;
    }
}
