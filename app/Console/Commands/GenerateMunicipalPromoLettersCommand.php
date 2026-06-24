<?php

namespace App\Console\Commands;

use App\Actions\Marketing\GenerateMunicipalPromoLettersAction;
use App\Models\User;
use App\Support\Marketing\PromoBaseUrl;
use Illuminate\Console\Command;
use RuntimeException;
use ZipArchive;

class GenerateMunicipalPromoLettersCommand extends Command
{
    protected $signature = 'marketing:generate-municipal-letters
                            {spreadsheet : Pad naar Vlaanderen_lokale_besturen.xlsx}
                            {--output=storage/app/municipal-promo-letters : Outputmap voor DOCX-brieven (niet in git)}
                            {--flow=public/images/promo/flow.jpg : Pad naar flow.jpg}
                            {--promo-base-url= : Basis-URL voor promo-QR (default: APP_URL)}
                            {--user= : Superuser ID of e-mail (default: eerste superuser)}
                            {--limit= : Maximaal aantal brieven}
                            {--force : Overschrijf bestaande DOCX-bestanden}
                            {--zip : Maak ook een ZIP van alle gegenereerde brieven}
                            {--allow-localhost-promo-url : Sta localhost toe (alleen lokaal testen)}';

    protected $description = 'Genereer promo-brieven per gemeente met unieke QR. Draai op productie voor juiste APP_URL en promo-logging.';

    public function handle(GenerateMunicipalPromoLettersAction $generateLetters): int
    {
        $promoBaseUrlOption = (string) $this->option('promo-base-url');
        $promoBaseUrl = PromoBaseUrl::resolve($promoBaseUrlOption !== '' ? $promoBaseUrlOption : null);

        if (PromoBaseUrl::isLocalhost($promoBaseUrl) && ! $this->option('allow-localhost-promo-url')) {
            $this->error('Promo-QR wijst naar localhost. Draai dit commando op de productieserver of geef --promo-base-url=https://jouw-domein op.');

            return self::FAILURE;
        }

        $actor = $this->resolveActorUser();
        if ($actor === null) {
            $this->error('Geen superuser gevonden. Geef --user=<id|email> mee.');

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $parsedLimit = $limit === null || $limit === '' ? null : max(1, (int) $limit);
        $outputDirectory = (string) $this->option('output');

        $this->info('Gemeente-brieven genereren (productie-flow)…');
        $this->line('Spreadsheet: '.$this->argument('spreadsheet'));
        $this->line('Output: '.$outputDirectory);
        $this->line('Promo-URL: '.$promoBaseUrl.'/promo?ref=…');
        $this->line('Actor: '.$actor->email.' (#'.$actor->id.')');

        $result = $generateLetters->handle(
            spreadsheetPath: (string) $this->argument('spreadsheet'),
            outputDirectory: $outputDirectory,
            flowImagePath: (string) $this->option('flow'),
            actorUserId: (int) $actor->id,
            promoBaseUrl: $promoBaseUrl,
            limit: $parsedLimit,
            overwriteExisting: (bool) $this->option('force'),
        );

        $zipPath = null;
        if ($this->option('zip')) {
            $zipPath = $this->createZipArchive($result['output_directory']);
        }

        $this->newLine();
        $this->info(sprintf(
            'Klaar: %d brieven gegenereerd, %d overgeslagen, %d nieuwe promo-bestemmelingen.',
            $result['generated'],
            $result['skipped'],
            $result['recipients_created'],
        ));
        $this->line('Map: '.$result['output_directory']);
        if ($zipPath !== null) {
            $this->line('ZIP: '.$zipPath);
        }

        return self::SUCCESS;
    }

    private function createZipArchive(string $outputDirectory): string
    {
        $resolvedDirectory = str_starts_with($outputDirectory, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $outputDirectory) === 1
            ? $outputDirectory
            : base_path($outputDirectory);

        $files = glob($resolvedDirectory.'/*.docx') ?: [];
        if ($files === []) {
            throw new RuntimeException('Geen DOCX-bestanden om te zippen in: '.$resolvedDirectory);
        }

        $zipPath = $resolvedDirectory.'.zip';
        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Kan ZIP niet aanmaken: '.$zipPath);
        }

        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }

        $zip->close();

        return $zipPath;
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
