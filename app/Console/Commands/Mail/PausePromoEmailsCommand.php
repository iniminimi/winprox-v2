<?php

declare(strict_types=1);

namespace App\Console\Commands\Mail;

use App\Actions\Marketing\PausePromoCampaignSendingAction;
use App\Actions\Marketing\ResumePromoCampaignSendingAction;
use App\Enums\PromoEmailsPauseReason;
use App\Models\PromoCampaign;
use Illuminate\Console\Command;

class PausePromoEmailsCommand extends Command
{
    protected $signature = 'marketing:pause-promo-emails
                            {--campaign= : Campagne-id of slug; weglaten = alle campagnes}
                            {--resume : Hervat verzending (zet niet opnieuw in de wachtrij)}
                            {--reason=cli : Pause reason: manual|domain_block|schedule|cli}';

    protected $description = 'Onderbreek of hervat promo-campagne mails (haalt wachtende jobs uit de queue)';

    public function handle(
        PausePromoCampaignSendingAction $pause,
        ResumePromoCampaignSendingAction $resume,
    ): int {
        $campaign = $this->resolveCampaign();
        if ($this->option('campaign') && $campaign === null) {
            $this->error('Campagne niet gevonden.');

            return self::FAILURE;
        }

        if ((bool) $this->option('resume')) {
            $result = $resume->handle($campaign, null);
            $this->info('Verzending hervat voor '.$result['resumed_campaigns'].' campagne(s). Zet daarna opnieuw mails in de wachtrij.');

            return self::SUCCESS;
        }

        $reason = PromoEmailsPauseReason::tryFrom((string) $this->option('reason'))
            ?? PromoEmailsPauseReason::Cli;

        $result = $pause->handle($campaign, null, $reason);
        $this->info('Verzending onderbroken ('.$reason->value.') voor '.$result['paused_campaigns'].' campagne(s). '.$result['purged_jobs'].' job(s) uit de wachtrij gehaald.');

        return self::SUCCESS;
    }

    private function resolveCampaign(): ?PromoCampaign
    {
        $value = trim((string) $this->option('campaign'));
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return PromoCampaign::query()->find((int) $value);
        }

        return PromoCampaign::query()->where('slug', $value)->first();
    }
}
