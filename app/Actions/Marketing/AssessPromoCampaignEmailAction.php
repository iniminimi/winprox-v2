<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Data\Marketing\PromoEmailAssessmentData;
use App\Enums\EmailUnsubscribeSource;
use App\Enums\PromoEmailPreflightReason;
use App\Models\EmailUnsubscribe;
use App\Support\EmailUnsubscribeExemptions;
use App\Support\Marketing\PromoEmailMxLookup;

class AssessPromoCampaignEmailAction
{
    public function __construct(private PromoEmailMxLookup $mxLookup) {}

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

        if (filter_var($raw, FILTER_VALIDATE_EMAIL) === false) {
            return new PromoEmailAssessmentData(
                hasEmail: true,
                accepted: false,
                normalizedEmail: null,
                reason: PromoEmailPreflightReason::InvalidSyntax,
            );
        }

        $normalized = EmailUnsubscribe::normalizeEmail($raw);

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

        $domain = substr(strrchr($normalized, '@') ?: '', 1);
        if ($domain === '' || ! $this->mxLookup->domainAcceptsMail($domain)) {
            return new PromoEmailAssessmentData(
                hasEmail: true,
                accepted: false,
                normalizedEmail: $normalized,
                reason: PromoEmailPreflightReason::NoMx,
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
