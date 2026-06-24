<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Data\Marketing\MunicipalPromoEmailCandidateData;
use App\Data\Marketing\MunicipalPromoLetterData;
use App\Enums\MunicipalPromoEmailSendStatus;
use App\Models\EmailUnsubscribe;
use App\Models\MunicipalPromoEmailSend;
use App\Models\PromoRecipient;
use App\Support\Marketing\FlemishMunicipalitiesSpreadsheetReader;
use App\Support\Marketing\MunicipalPromoEmailBlockReason;
use App\Support\Marketing\PromoBaseUrl;
use App\Support\Marketing\PromoLandingUrl;
use RuntimeException;

class ListMunicipalPromoEmailCandidatesAction
{
    public function __construct(
        private FlemishMunicipalitiesSpreadsheetReader $spreadsheetReader,
    ) {}

    /**
     * @return list<MunicipalPromoEmailCandidateData>
     */
    public function handle(
        string $spreadsheetPath,
        string $lettersDirectory,
        string $promoBaseUrl,
        string $campaign,
        ?int $limit = null,
        ?string $municipalityFilter = null,
        bool $forceResend = false,
        ?string $overrideRecipientEmail = null,
    ): array {
        $spreadsheetPath = $this->resolvePath($spreadsheetPath);
        $lettersDirectory = $this->resolvePath($lettersDirectory);
        $promoBaseUrl = PromoBaseUrl::resolve($promoBaseUrl);
        $campaign = trim($campaign);
        $municipalityFilter = $municipalityFilter !== null ? trim($municipalityFilter) : null;
        $overrideRecipientEmail = $overrideRecipientEmail !== null ? trim($overrideRecipientEmail) : null;
        if ($overrideRecipientEmail === '') {
            $overrideRecipientEmail = null;
        }

        if ($campaign === '') {
            throw new RuntimeException('Campaign is required.');
        }

        $municipalities = $this->spreadsheetReader->read($spreadsheetPath);
        if ($municipalityFilter !== null && $municipalityFilter !== '') {
            $municipalities = array_values(array_filter(
                $municipalities,
                static fn (MunicipalPromoLetterData $row): bool => strcasecmp($row->name, $municipalityFilter) === 0,
            ));
        }

        if ($limit !== null) {
            $municipalities = array_slice($municipalities, 0, max(0, $limit));
        }

        $sentMunicipalities = MunicipalPromoEmailSend::query()
            ->where('campaign', $campaign)
            ->where('status', MunicipalPromoEmailSendStatus::Sent)
            ->pluck('municipality_name')
            ->map(static fn (string $name): string => mb_strtolower($name))
            ->all();

        $candidates = [];
        foreach ($municipalities as $municipality) {
            $candidates[] = $this->buildCandidate(
                municipality: $municipality,
                lettersDirectory: $lettersDirectory,
                promoBaseUrl: $promoBaseUrl,
                sentMunicipalities: $sentMunicipalities,
                forceResend: $forceResend,
                overrideRecipientEmail: $overrideRecipientEmail,
            );
        }

        return $candidates;
    }

    /**
     * @param  list<string>  $sentMunicipalities
     */
    private function buildCandidate(
        MunicipalPromoLetterData $municipality,
        string $lettersDirectory,
        string $promoBaseUrl,
        array $sentMunicipalities,
        bool $forceResend,
        ?string $overrideRecipientEmail = null,
    ): MunicipalPromoEmailCandidateData {
        $email = trim((string) ($municipality->email ?? ''));
        $docxPath = $lettersDirectory.DIRECTORY_SEPARATOR.$municipality->slug().'.docx';
        $recipient = PromoRecipient::query()->where('label', $municipality->name)->first();
        $promoToken = $recipient?->token ?? '';
        $promoUrl = $promoToken !== ''
            ? PromoLandingUrl::forRecipientTokenOnBaseUrl($promoToken, $promoBaseUrl)
            : '';

        $blockReason = $this->resolveBlockReason(
            email: $email,
            docxPath: $docxPath,
            recipient: $recipient,
            municipalityName: $municipality->name,
            sentMunicipalities: $sentMunicipalities,
            forceResend: $forceResend,
            overrideRecipientEmail: $overrideRecipientEmail,
        );

        return new MunicipalPromoEmailCandidateData(
            municipality: $municipality,
            promoRecipientId: $recipient?->id,
            promoToken: $promoToken,
            promoUrl: $promoUrl,
            docxPath: $docxPath,
            recipientEmail: $email,
            blockReason: $blockReason,
        );
    }

    /**
     * @param  list<string>  $sentMunicipalities
     */
    private function resolveBlockReason(
        string $email,
        string $docxPath,
        ?PromoRecipient $recipient,
        string $municipalityName,
        array $sentMunicipalities,
        bool $forceResend,
        ?string $overrideRecipientEmail = null,
    ): ?string {
        $deliveryEmail = $overrideRecipientEmail ?? $email;

        if ($overrideRecipientEmail === null) {
            if ($email === '') {
                return MunicipalPromoEmailBlockReason::MISSING_EMAIL;
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                return MunicipalPromoEmailBlockReason::INVALID_EMAIL;
            }
        } elseif (filter_var($overrideRecipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            return MunicipalPromoEmailBlockReason::INVALID_EMAIL;
        }

        if (! is_file($docxPath)) {
            return MunicipalPromoEmailBlockReason::MISSING_DOCX;
        }

        if (! $recipient instanceof PromoRecipient) {
            return MunicipalPromoEmailBlockReason::MISSING_PROMO_RECIPIENT;
        }

        if (! $forceResend && in_array(mb_strtolower($municipalityName), $sentMunicipalities, true)) {
            return MunicipalPromoEmailBlockReason::ALREADY_SENT;
        }

        if (EmailUnsubscribe::isUnsubscribed($deliveryEmail)) {
            return MunicipalPromoEmailBlockReason::UNSUBSCRIBED;
        }

        return null;
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
