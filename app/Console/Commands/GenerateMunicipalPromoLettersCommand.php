<?php

namespace App\Console\Commands;

use App\Actions\Marketing\GenerateMunicipalPromoLettersAction;
use App\Models\User;
use Illuminate\Console\Command;

class GenerateMunicipalPromoLettersCommand extends Command
{
    protected $signature = 'marketing:generate-municipal-letters
                            {spreadsheet : Pad naar Vlaanderen_lokale_besturen.xlsx}
                            {--output=storage/app/municipal-promo-letters : Lokale outputmap voor DOCX-brieven}
                            {--flow=public/images/promo/flow.jpg : Pad naar flow.jpg}
                            {--user= : Superuser ID of e-mail (default: eerste superuser)}
                            {--limit= : Maximaal aantal brieven}
                            {--force : Overschrijf bestaande DOCX-bestanden}';

    protected $description = 'Genereer lokale promo-brieven per Vlaamse gemeente met unieke QR (bestaande promo-tracking).';

    public function handle(GenerateMunicipalPromoLettersAction $generateLetters): int
    {
        $actor = $this->resolveActorUser();
        if ($actor === null) {
            $this->error('Geen superuser gevonden. Geef --user=<id|email> mee.');

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $parsedLimit = $limit === null || $limit === '' ? null : max(1, (int) $limit);

        $this->info('Gemeente-brieven genereren…');
        $this->line('Spreadsheet: '.$this->argument('spreadsheet'));
        $this->line('Output: '.$this->option('output'));
        $this->line('Actor: '.$actor->email.' (#'.$actor->id.')');

        $result = $generateLetters->handle(
            spreadsheetPath: (string) $this->argument('spreadsheet'),
            outputDirectory: (string) $this->option('output'),
            flowImagePath: (string) $this->option('flow'),
            actorUserId: (int) $actor->id,
            limit: $parsedLimit,
            overwriteExisting: (bool) $this->option('force'),
        );

        $this->newLine();
        $this->info(sprintf(
            'Klaar: %d brieven gegenereerd, %d overgeslagen, %d nieuwe promo-bestemmelingen.',
            $result['generated'],
            $result['skipped'],
            $result['recipients_created'],
        ));
        $this->line('Map: '.$result['output_directory']);

        return self::SUCCESS;
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
