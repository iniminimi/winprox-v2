<?php

namespace App\Console\Commands;

use App\Actions\Marketing\ListMunicipalPromoEmailCandidatesAction;
use App\Actions\Marketing\QueueMunicipalPromoLetterEmailsAction;
use App\Actions\Marketing\SendMunicipalPromoLetterEmailAction;
use App\Data\Marketing\MunicipalPromoEmailCandidateData;
use App\Models\User;
use App\Support\Marketing\MunicipalPromoEmailBlockReason;
use App\Support\Marketing\PromoBaseUrl;
use Illuminate\Console\Command;

class SendMunicipalPromoLettersEmailCommand extends Command
{
    protected $signature = 'marketing:send-municipal-promo-emails
                            {spreadsheet : Pad naar Vlaanderen_lokale_besturen.xlsx}
                            {--letters-dir=storage/app/municipal-promo-letters : Map met DOCX-brieven}
                            {--campaign=wave-1 : Campagne-id voor send-log}
                            {--promo-base-url= : Basis-URL voor promo-links (default: APP_URL)}
                            {--user= : Superuser ID of e-mail (default: eerste superuser)}
                            {--limit= : Maximaal aantal gemeenten}
                            {--municipality= : Alleen deze gemeente (exacte naam)}
                            {--delay-seconds=90 : Vertraging tussen queue-jobs}
                            {--override-to= : Alle mails naar dit adres (test)}
                            {--audit : Toon alleen statistieken}
                            {--dry-run : Toon verzendlijst zonder te versturen}
                            {--send : Plan verzending (queue) of sync}
                            {--sync : Verstuur direct zonder queue (kleine tests)}
                            {--confirm : Vereist bij --send}
                            {--force : Opnieuw versturen ook als al verzonden}';

    protected $description = 'Verstuur gemeente-promomails met DOCX-bijlage en unieke promo-link. Eerst --audit en --dry-run.';

    public function handle(
        ListMunicipalPromoEmailCandidatesAction $listCandidates,
        QueueMunicipalPromoLetterEmailsAction $queueEmails,
        SendMunicipalPromoLetterEmailAction $sendEmail,
    ): int {
        $promoBaseUrl = PromoBaseUrl::resolve(
            ($this->option('promo-base-url') ?: null) !== '' ? (string) $this->option('promo-base-url') : null,
        );

        if (PromoBaseUrl::isLocalhost($promoBaseUrl)) {
            $this->warn('Promo-links wijzen naar localhost. Gebruik productie-APP_URL of --promo-base-url=https://winprox.app');
        }

        $actor = $this->resolveActorUser();
        if ($actor === null) {
            $this->error('Geen superuser gevonden. Geef --user=<id|email> mee.');

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $parsedLimit = $limit === null || $limit === '' ? null : max(1, (int) $limit);
        $campaign = (string) $this->option('campaign');
        $spreadsheet = (string) $this->argument('spreadsheet');
        $lettersDir = (string) $this->option('letters-dir');
        $municipality = (string) $this->option('municipality');
        $force = (bool) $this->option('force');
        $overrideTo = trim((string) $this->option('override-to'));
        $overrideTo = $overrideTo !== '' ? $overrideTo : null;

        $candidates = $listCandidates->handle(
            spreadsheetPath: $spreadsheet,
            lettersDirectory: $lettersDir,
            promoBaseUrl: $promoBaseUrl,
            campaign: $campaign,
            limit: $parsedLimit,
            municipalityFilter: $municipality !== '' ? $municipality : null,
            forceResend: $force,
            overrideRecipientEmail: $overrideTo,
        );

        if ($this->option('audit')) {
            $this->renderAudit($candidates);

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->renderDryRun($candidates, $overrideTo);

            return self::SUCCESS;
        }

        if (! $this->option('send')) {
            $this->error('Geen modus gekozen. Gebruik --audit, --dry-run of --send --confirm.');

            return self::FAILURE;
        }

        if (! $this->option('confirm')) {
            $this->error('Voeg --confirm toe om daadwerkelijk te versturen.');

            return self::FAILURE;
        }

        $ready = array_values(array_filter(
            $candidates,
            static fn (MunicipalPromoEmailCandidateData $candidate): bool => $candidate->isReady(),
        ));

        if ($ready === []) {
            if ($candidates === [] && $municipality !== '') {
                $this->warn('Gemeente "'.$municipality.'" niet gevonden in spreadsheet.');
            } else {
                $this->warn('Geen verzendbare gemeenten.');
                foreach ($candidates as $candidate) {
                    $this->line(sprintf(
                        '  - %s: %s',
                        $candidate->municipality->name,
                        $candidate->blockReason ?? 'onbekend',
                    ));
                }
                $this->line('Tip: draai --dry-run voor details (DOCX-pad, promo-URL).');
            }

            return self::FAILURE;
        }

        $fromAddress = (string) config('winprox.municipal_promo_email_from.address');
        $this->info('Gemeente-promomails versturen…');
        $this->line('Spreadsheet: '.$spreadsheet);
        $this->line('Brieven: '.$lettersDir);
        $this->line('Campagne: '.$campaign);
        $this->line('Van: '.$fromAddress);
        $this->line('Klaar om te versturen: '.count($ready));
        if ($overrideTo !== null) {
            $this->line('Override naar: '.$overrideTo);
        }

        if ($this->option('sync')) {
            $sent = 0;
            $delaySeconds = max(0, (int) $this->option('delay-seconds'));

            foreach ($ready as $index => $candidate) {
                if ($index > 0 && $delaySeconds > 0) {
                    sleep($delaySeconds);
                }

                $sendEmail->handle(
                    candidate: $candidate,
                    campaign: $campaign,
                    actorUserId: (int) $actor->id,
                    overrideRecipientEmail: $overrideTo,
                );
                $sent++;
                $this->line('Verstuurd: '.$candidate->municipality->name);
            }

            $this->info("Klaar: {$sent} mail(s) direct verstuurd.");

            return self::SUCCESS;
        }

        $result = $queueEmails->handle(
            candidates: $candidates,
            campaign: $campaign,
            spreadsheetPath: $spreadsheet,
            lettersDirectory: $lettersDir,
            promoBaseUrl: $promoBaseUrl,
            actorUserId: (int) $actor->id,
            delaySeconds: (int) $this->option('delay-seconds'),
            overrideRecipientEmail: $overrideTo,
            forceResend: $force,
        );

        $this->info(sprintf(
            'Klaar: %d job(s) in queue, %d overgeslagen. Zorg dat queue:work draait.',
            $result['queued'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }

    /**
     * @param  list<MunicipalPromoEmailCandidateData>  $candidates
     */
    private function renderAudit(array $candidates): void
    {
        $counts = [];
        $ready = 0;

        foreach ($candidates as $candidate) {
            if ($candidate->isReady()) {
                $ready++;
                continue;
            }

            $reason = (string) $candidate->blockReason;
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
        }

        $this->info('Audit gemeente-promomails');
        $this->line('Totaal in selectie: '.count($candidates));
        $this->line('Verzendbaar: '.$ready);

        foreach ($counts as $reason => $count) {
            $this->line($this->blockReasonLabel($reason).': '.$count);
        }
    }

    /**
     * @param  list<MunicipalPromoEmailCandidateData>  $candidates
     */
    private function renderDryRun(array $candidates, ?string $overrideTo): void
    {
        $this->info('Dry-run gemeente-promomails');
        $rows = [];

        foreach ($candidates as $candidate) {
            $rows[] = [
                $candidate->municipality->name,
                $overrideTo ?? $candidate->recipientEmail,
                basename($candidate->docxPath),
                $candidate->isReady() ? 'ok' : $candidate->blockReason,
                $candidate->promoUrl,
            ];
        }

        $this->table(
            ['Gemeente', 'E-mail', 'DOCX', 'Status', 'Promo-URL'],
            $rows,
        );
    }

    private function blockReasonLabel(string $reason): string
    {
        return match ($reason) {
            MunicipalPromoEmailBlockReason::MISSING_EMAIL => 'Zonder e-mail',
            MunicipalPromoEmailBlockReason::INVALID_EMAIL => 'Ongeldig e-mailadres',
            MunicipalPromoEmailBlockReason::MISSING_DOCX => 'DOCX ontbreekt',
            MunicipalPromoEmailBlockReason::MISSING_PROMO_RECIPIENT => 'Geen promo-bestemmeling',
            MunicipalPromoEmailBlockReason::ALREADY_SENT => 'Al verzonden',
            MunicipalPromoEmailBlockReason::UNSUBSCRIBED => 'Uitgeschreven',
            default => $reason,
        };
    }

    private function resolveActorUser(): ?User
    {
        $identifier = (string) $this->option('user');
        if ($identifier !== '') {
            return is_numeric($identifier)
                ? User::query()->where('is_superuser', true)->find((int) $identifier)
                : User::query()->where('is_superuser', true)->where('email', $identifier)->first();
        }

        return User::query()->where('is_superuser', true)->orderBy('id')->first();
    }
}
