<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Data\Marketing\MunicipalPromoLetterData;
use App\Models\PromoRecipient;
use App\Support\Marketing\FlemishMunicipalitiesSpreadsheetReader;
use App\Support\Marketing\MunicipalPromoLetterDocxBuilder;
use App\Support\Marketing\PromoLandingUrl;
use RuntimeException;

class GenerateMunicipalPromoLettersAction
{
    public function __construct(
        private FlemishMunicipalitiesSpreadsheetReader $spreadsheetReader,
        private MunicipalPromoLetterDocxBuilder $letterBuilder,
        private CreatePromoRecipientAction $createPromoRecipient,
    ) {}

    /**
     * @return array{
     *     generated: int,
     *     skipped: int,
     *     recipients_created: int,
     *     output_directory: string,
     * }
     */
    public function handle(
        string $spreadsheetPath,
        string $outputDirectory,
        string $flowImagePath,
        int $actorUserId,
        ?int $limit = null,
        bool $overwriteExisting = false,
    ): array {
        $spreadsheetPath = $this->resolvePath($spreadsheetPath);
        $outputDirectory = $this->resolvePath($outputDirectory);
        $flowImagePath = $this->resolvePath($flowImagePath);

        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0777, true) && ! is_dir($outputDirectory)) {
            throw new RuntimeException("Unable to create output directory: {$outputDirectory}");
        }

        $municipalities = $this->spreadsheetReader->read($spreadsheetPath);
        if ($limit !== null) {
            $municipalities = array_slice($municipalities, 0, max(0, $limit));
        }

        $generated = 0;
        $skipped = 0;
        $recipientsCreated = 0;

        foreach ($municipalities as $municipality) {
            $outputPath = $outputDirectory.DIRECTORY_SEPARATOR.$municipality->slug().'.docx';
            if (is_file($outputPath) && ! $overwriteExisting) {
                $skipped++;
                continue;
            }

            $recipient = $this->resolvePromoRecipient($municipality, $actorUserId);
            if ($recipient['created']) {
                $recipientsCreated++;
            }

            $promoUrl = PromoLandingUrl::forRecipientToken($recipient['recipient']->token);

            $this->letterBuilder->build(
                municipality: $municipality,
                promoUrl: $promoUrl,
                flowImagePath: $flowImagePath,
                outputPath: $outputPath,
            );

            $generated++;
        }

        return [
            'generated' => $generated,
            'skipped' => $skipped,
            'recipients_created' => $recipientsCreated,
            'output_directory' => $outputDirectory,
        ];
    }

    /**
     * @return array{recipient: PromoRecipient, created: bool}
     */
    private function resolvePromoRecipient(
        MunicipalPromoLetterData $municipality,
        int $actorUserId,
    ): array {
        $existing = PromoRecipient::query()
            ->where('label', $municipality->name)
            ->first();

        if ($existing instanceof PromoRecipient) {
            return ['recipient' => $existing, 'created' => false];
        }

        $note = trim($municipality->province);
        if ($municipality->municipalityType !== '') {
            $note = trim($municipality->municipalityType.($note !== '' ? ' · '.$note : ''));
        }

        return [
            'recipient' => $this->createPromoRecipient->handle(
                label: $municipality->name,
                note: $note !== '' ? $note : null,
                actorUserId: $actorUserId,
            ),
            'created' => true,
        ];
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('Path is required.');
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
