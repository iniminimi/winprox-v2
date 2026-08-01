<?php

namespace App\Support\Recurrence;

use App\Enums\RecurrenceIntervalUnit;
use Carbon\Carbon;

final class RecurrenceSchedule
{
    public static function nextDueAt(Carbon $dueAt, int $intervalValue, RecurrenceIntervalUnit|string $unit): Carbon
    {
        $value = max(1, $intervalValue);
        $unitValue = $unit instanceof RecurrenceIntervalUnit ? $unit->value : (string) $unit;

        return match ($unitValue) {
            RecurrenceIntervalUnit::Day->value => $dueAt->copy()->addDays($value),
            RecurrenceIntervalUnit::Week->value => $dueAt->copy()->addWeeks($value),
            RecurrenceIntervalUnit::Month->value => $dueAt->copy()->addMonthsNoOverflow($value),
            RecurrenceIntervalUnit::Quarter->value => $dueAt->copy()->addMonthsNoOverflow($value * 3),
            RecurrenceIntervalUnit::Year->value => $dueAt->copy()->addYearsNoOverflow($value),
            default => $dueAt->copy()->addYearsNoOverflow($value),
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function issueAttributesFromValidated(array $validated): array
    {
        $isRecurring = (bool) ($validated['is_recurring'] ?? false);

        if (! $isRecurring) {
            return [
                'is_recurring' => false,
                'recurrence_interval_value' => null,
                'recurrence_interval_unit' => null,
                'recurrence_lead_days' => 30,
                'recurrence_active' => true,
                'recurrence_next_due_at' => null,
            ];
        }

        $firstDue = Carbon::parse((string) $validated['recurrence_first_due_date'])->endOfDay();

        return [
            'is_recurring' => true,
            'recurrence_interval_value' => (int) $validated['recurrence_interval_value'],
            'recurrence_interval_unit' => (string) $validated['recurrence_interval_unit'],
            'recurrence_lead_days' => (int) ($validated['recurrence_lead_days'] ?? 30),
            'recurrence_active' => true,
            'recurrence_next_due_at' => $firstDue,
        ];
    }
}
