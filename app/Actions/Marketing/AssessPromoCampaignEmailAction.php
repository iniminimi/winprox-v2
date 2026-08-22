<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Data\Marketing\PromoEmailAssessmentData;
use App\Enums\EmailUnsubscribeSource;
use App\Enums\PromoEmailPreflightReason;
use App\Models\EmailUnsubscribe;
use App\Support\EmailUnsubscribeExemptions;
use App\Support\Marketing\PromoEmailAddressSanitizer;

class AssessPromoCampaignEmailAction
{
    public function handle(?string $email): PromoEmailAssessmentData
    {
        $raw = trim((string) $email);
        if ($raw === '') {
            return new PromoEmailAssessmentData(
                hasEmail: false,
                accepted: false,
                normalizedEmail: null,
                reason: null,
            );
        }

        $normalized = PromoEmailAddressSanitizer::sanitize($raw);
        if ($normalized === null) {
            return new PromoEmailAssessmentData(
                hasEmail: true,
                accepted: false,
                normalizedEmail: null,
                reason: PromoEmailPreflightReason::InvalidSyntax,
            );
        }

        if (
            EmailUnsubscribe::isUnsubscribed($normalized)
            && ! EmailUnsubscribeExemptions::isExempt($normalized)
        ) {
            $row = EmailUnsubscribe::query()->where('email', $normalized)->first();
            $reason = $row?->source === EmailUnsubscribeSource::Undeliverable
                ? PromoEmailPreflightReason::PreviouslyBounced
                : PromoEmailPreflightReason::Unsubscribed;

            return new PromoEmailAssessmentData(
                hasEmail: true,
                accepted: false,
                normalizedEmail: $normalized,
                reason: $reason,
            );
        }

        return new PromoEmailAssessmentData(
            hasEmail: true,
            accepted: true,
            normalizedEmail: $normalized,
            reason: null,
        );
    }
}
