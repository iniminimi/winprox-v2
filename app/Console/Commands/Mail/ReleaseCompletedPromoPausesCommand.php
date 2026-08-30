<?php

declare(strict_types=1);

namespace App\Console\Commands\Mail;

use App\Actions\Marketing\ReleasePromoCampaignPauseIfCompleteAction;
use App\Models\PromoCampaign;
use Illuminate\Console\Command;

class ReleaseCompletedPromoPausesCommand extends Command
{
    protected $signature = 'marketing:release-completed-promo-pauses';

    protected $description = 'Zet promo-campagne pause terug wanneer alle mails verstuurd zijn';

    public function handle(ReleasePromoCampaignPauseIfCompleteAction $release): int
    {
        $paused = PromoCampaign::query()
            ->whereNotNull('emails_paused_at')
            ->get();

        $released = $release->handleCollection($paused);

        $this->info('Pause vrijgegeven voor '.$released.' campagne(s).');

        return self::SUCCESS;
    }
}
