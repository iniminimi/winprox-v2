<?php

declare(strict_types=1);

use App\Support\Time\WorkDurationFormatter;

beforeEach(fn () => app()->setLocale('nl'));

it('formatteert werkminuten menselijk leesbaar', function (int $minutes, string $expected) {
    expect(WorkDurationFormatter::format($minutes))->toBe($expected);
})->with([
    'nul' => [0, '0min'],
    'onder een uur' => [45, '45min'],
    'uur en minuten' => [80, '1u20min'],
    'exact uur' => [120, '2u'],
    'meerdere uren' => [125, '2u5min'],
]);

it('formatteert negatieve minuten als nul', function () {
    expect(WorkDurationFormatter::format(-5))->toBe('0min');
});
