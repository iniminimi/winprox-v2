<?php

declare(strict_types=1);

use App\Actions\Units\SuggestNotOkReportPrefillAction;
use App\Enums\UnitCheckResult;
use Carbon\CarbonImmutable;

it('suggests a report prefill after not_ok on an inspection round', function () {
    $checkedAt = CarbonImmutable::parse('2026-08-04 14:18:00', 'Europe/Brussels');

    $prefill = app(SuggestNotOkReportPrefillAction::class)->handle(
        appliedToInspectionRound: true,
        result: UnitCheckResult::NotOk,
        checkedAt: $checkedAt,
        timezone: 'Europe/Brussels',
    );

    expect($prefill)->toBe(__('portal.unit_check.report_prefill_not_ok_round', [
        'datetime' => '04-08-2026 14:18',
    ]));
});

it('does not suggest a report prefill outside an inspection round', function () {
    $prefill = app(SuggestNotOkReportPrefillAction::class)->handle(
        appliedToInspectionRound: false,
        result: UnitCheckResult::NotOk,
        checkedAt: CarbonImmutable::now(),
        timezone: 'UTC',
    );

    expect($prefill)->toBeNull();
});

it('does not overwrite an existing report description', function () {
    $prefill = app(SuggestNotOkReportPrefillAction::class)->handle(
        appliedToInspectionRound: true,
        result: UnitCheckResult::NotOk,
        checkedAt: CarbonImmutable::now(),
        timezone: 'UTC',
        existingDescription: 'Deur ging niet open',
    );

    expect($prefill)->toBeNull();
});

it('does not suggest a report prefill after ok', function () {
    $prefill = app(SuggestNotOkReportPrefillAction::class)->handle(
        appliedToInspectionRound: true,
        result: UnitCheckResult::Ok,
        checkedAt: CarbonImmutable::now(),
        timezone: 'UTC',
    );

    expect($prefill)->toBeNull();
});
