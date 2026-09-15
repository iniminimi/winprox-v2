<?php

namespace App\Actions\Time;

use App\Data\Time\SaveShiftTypeData;
use App\Events\Time\ShiftTypeSaved;
use App\Exceptions\RosterValidationException;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Enums\RosterCellKind;
use App\Support\Time\TimeModuleAccess;

class SaveShiftTypeAction
{
    public function __construct(private ParseRosterCellAction $parseCell) {}

    public function handle(Tenant $tenant, SaveShiftTypeData $data, ?int $actorUserId, ?int $shiftTypeId = null): ShiftType
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        $code = ShiftType::normalizeCode($data->code);
        if ($code === '' || strlen($code) > 8 || ! preg_match('/^[A-Z0-9]+$/', $code)) {
            throw new RosterValidationException('time.schedule.errors.invalid_code');
        }

        [$startTime, $endTime] = $this->normalizedWindow($data->startTime, $data->endTime);

        $duplicate = ShiftType::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', $code)
            ->when($shiftTypeId !== null, fn ($q) => $q->whereKeyNot($shiftTypeId))
            ->exists();

        if ($duplicate) {
            throw new RosterValidationException('time.schedule.errors.code_taken');
        }

        $payload = [
            'tenant_id' => $tenant->id,
            'code' => $code,
            'label' => trim($data->label),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'break_minutes' => $data->breakMinutes,
            'color' => $data->color,
            'is_active' => $data->isActive,
        ];

        if ($shiftTypeId !== null) {
            $shiftType = ShiftType::query()->whereKey($shiftTypeId)->firstOrFail();
            $shiftType->update($payload);
        } else {
            $shiftType = ShiftType::create($payload);
        }

        $fresh = $shiftType->fresh();
        event(new ShiftTypeSaved($fresh, $actorUserId));

        return $fresh;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalizedWindow(string $start, string $end): array
    {
        $parsed = $this->parseCell->handle($start.'-'.$end, collect());
        if ($parsed->kind !== RosterCellKind::FreeTime || $parsed->startTime === null || $parsed->endTime === null) {
            throw new RosterValidationException($parsed->errorKey ?? 'time.schedule.errors.invalid_time');
        }

        return [$parsed->startTime, $parsed->endTime];
    }
}
