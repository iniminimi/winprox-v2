<?php

namespace App\Console\Commands;

use App\Actions\Time\FinalizeExpiredClockPointQrGraceTokensAction;
use Illuminate\Console\Command;

class FinalizeExpiredClockPointQrGraceTokensCommand extends Command
{
    protected $signature = 'winprox:time-finalize-qr-grace';

    protected $description = 'Blokkeer verlopen Clock Point QR-tokens na de grace-periode';

    public function handle(FinalizeExpiredClockPointQrGraceTokensAction $finalize): int
    {
        $count = $finalize->handle();
        $this->info("Geblokkeerde QR-tokens: {$count}");

        return self::SUCCESS;
    }
}
