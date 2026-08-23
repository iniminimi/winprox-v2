<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Models\PromoCampaign;
use App\Models\PromoCampaignTarget;
use App\Models\PromoRecipient;
use App\Support\Marketing\PromoBaseUrl;
use App\Support\Marketing\PromoCampaignLetterDocxBuilder;
use App\Support\Marketing\PromoCampaignPlaceholderRenderer;
use App\Support\Marketing\PromoLandingUrl;
use RuntimeException;

class GeneratePromoCampaignLetterForTargetAction
{
    public function __construct(
        private PromoCampaignLetterDocxBuilder $letterBuilder,
        private CreatePromoRecipientAction $createPromoRecipient,
    ) {}

    public function handle(
        PromoCampaign $campaign,
        PromoCampaignTarget $target,
        int $actorUserId,
        string $promoBaseUrl,
        bool $overwriteExisting = false,
    ): PromoCampaignTarget {
        if ((int) $target->promo_campaign_id !== (int) $campaign->id) {
            throw new RuntimeException('Target does not belong to campaign.');
        }

        $promoBaseUrl = PromoBaseUrl::resolve($promoBaseUrl);
        $outputDirectory = $campaign->lettersDirectory();
        $filename = $target->slug().'.docx';
        $outputPath = $outputDirectory.DIRECTORY_SEPARATOR.$filename;

        if (is_file($outputPath) && ! $overwriteExisting && $target->generated_at !== null) {
            return $target;
        }

        $recipient = $this->resolvePromoRecipient($campaign, $target, $actorUserId);
        $promoUrl = PromoLandingUrl::forRecipientTokenOnBaseUrl(
            $recipient->token,
            $promoBaseUrl,
            $campaign->locale,
            $campaign->landing,
        );
        $welcomeUrl = PromoLandingUrl::welcomeForRecipientTokenOnBaseUrl($recipient->token, $promoBaseUrl, $campaign->locale);

        $placeholders = PromoCampaignPlaceholderRenderer::forTarget(
            name: $target->name,
            streetAddress: $target->street_address,
            postalCode: $target->postal_code,
            city: $target->city,
            email: $target->email,
            promoUrl: $promoUrl,
            welcomeUrl: $welcomeUrl,
        );

        $flowPath = $campaign->flow_image_path;
        if ($flowPath !== null && ! str_starts_with($flowPath, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $flowPath)) {
            $flowPath = base_path($flowPath);
        }

        $this->letterBuilder->build(
            locale: $campaign->locale,
            placeholders: $placeholders,
            letterBodyHtml: (string) ($campaign->letter_body_html ?? ''),
            flowImagePath: $flowPath,
            promoUrl: $promoUrl,
            outputPath: $outputPath,
        );

        $target->update([
            'promo_recipient_id' => $recipient->id,
            'docx_filename' => $filename,
            'generated_at' => now(),
        ]);

        return $target->fresh() ?? $target;
    }

    private function resolvePromoRecipient(
        PromoCampaign $campaign,
        PromoCampaignTarget $target,
        int $actorUserId,
    ): PromoRecipient {
        if ($target->promo_recipient_id !== null) {
            $existing = PromoRecipient::query()->find($target->promo_recipient_id);
            if ($existing instanceof PromoRecipient) {
                return $existing;
            }
        }

        $byLabel = PromoRecipient::query()->where('label', $target->name)->first();
        if ($byLabel instanceof PromoRecipient) {
            return $byLabel;
        }

        return $this->createPromoRecipient->handle(
            label: $target->name,
            note: $campaign->name,
            actorUserId: $actorUserId,
            recordAudit: false,
        );
    }
}
