<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Data\Marketing\MunicipalPromoLetterData;
use App\Models\PromoRecipient;
use App\Support\Marketing\FlemishMunicipalitiesSpreadsheetReader;
use App\Support\Marketing\MunicipalPromoRecipientManifest;
use RuntimeException;

class SyncMunicipalPromoRecipientsAction
{
    public function __construct(
        private FlemishMunicipalitiesSpreadsheetReader $spreadsheetReader,
        private CreatePromoRecipientAction $createPromoRecipient,
    ) {}

    /**
     * @return array{
     *     synced: int,
     *     created: int,
     *     manifest_path: string,
     * }
     */
    public function handle(
        string $spreadsheetPath,
        string $manifestPath,
        int $actorUserId,
        string $promoAppUrl,
        ?int $limit = null,
    ): array {
        $spreadsheetPath = $this->resolvePath($spreadsheetPath);
        $manifestPath = $this->resolvePath($manifestPath);
        $promoAppUrl = rtrim($promoAppUrl, '/');
        if ($promoAppUrl === '') {
            throw new RuntimeException('promo_app_url is required.');
        }

        $manifest = is_file($manifestPath)
            ? MunicipalPromoRecipientManifest::read($manifestPath)
            : new MunicipalPromoRecipientManifest($promoAppUrl, []);

        if ($manifest->promoAppUrl !== $promoAppUrl) {
            $manifest = new MunicipalPromoRecipientManifest($promoAppUrl, $manifest->recipients);
        }

        $municipalities = $this->spreadsheetReader->read($spreadsheetPath);
        if ($limit !== null) {
            $municipalities = array_slice($municipalities, 0, max(0, $limit));
        }

        $synced = 0;
        $created = 0;

        foreach ($municipalities as $municipality) {
            $recipient = $this->resolvePromoRecipient($municipality, $actorUserId);
            if ($recipient['created']) {
                $created++;
            }

            $manifest = $manifest->withRecipient($municipality->name, $recipient['recipient']->token);
            $synced++;
        }

        $manifest->write($manifestPath);

        return [
            'synced' => $synced,
            'created' => $created,
            'manifest_path' => $manifestPath,
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
                recordAudit: false,
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
