<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Enums\UnitCheckResult;
use Carbon\CarbonImmutable;

/**
 * Prefill-tekst voor het meldformulier na Niet OK op een inspectieronde-stop.
 */
class SuggestNotOkReportPrefillAction
{
    public function handle(
        bool $appliedToInspectionRound,
        UnitCheckResult $result,
        CarbonImmutable $checkedAt,
        string $timezone,
        string $existingDescription = '',
    ): ?string {
        if ($result !== UnitCheckResult::NotOk || ! $appliedToInspectionRound) {
            return null;
        }

        if (trim($existingDescription) !== '') {
            return null;
        }

        return __('portal.unit_check.report_prefill_not_ok_round', [
            'datetime' => $checkedAt->timezone($timezone)->format('d-m-Y H:i'),
        ]);
    }
}
