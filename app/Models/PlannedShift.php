<?php

namespace App\Models;

use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlannedShift extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'worker_id',
        'work_date',
        'shift_type_id',
        'kind',
        'unit_id',
        'unit_code',
        'unit_name',
        'location_id',
        'start_time',
        'end_time',
        'day_portion',
        'break_minutes',
        'description',
        'status',
    ];

    protected $casts = [
        'work_date' => 'date',
        'kind' => ShiftTypeKind::class,
        'break_minutes' => 'integer',
        'status' => PlannedShiftStatus::class,
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ShiftType::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isReplacementPool(): bool
    {
        return $this->shiftType !== null
            && $this->shiftType->is_replacement_pool
            && $this->kind !== ShiftTypeKind::Sick;
    }

    public function overlapsMinutes(int $start, int $end): bool
    {
        if ($this->start_time === null || $this->end_time === null || $this->kind->isAbsence()) {
            return false;
        }

        $blockStart = ShiftType::timeToMinutes((string) $this->start_time);
        $blockEnd = ShiftType::timeToMinutes((string) $this->end_time);

        return $blockStart < $end && $start < $blockEnd;
    }

    public function startMinutes(): int
    {
        return ShiftType::timeToMinutes($this->start_time);
    }

    public function endMinutes(): int
    {
        return ShiftType::timeToMinutes($this->end_time);
    }

    public function displayValue(): string
    {
        $duty = $this->dutyDisplay();
        if (is_string($this->unit_code) && $this->unit_code !== '') {
            return $duty === '' ? $this->unit_code : $duty.'/'.$this->unit_code;
        }

        return $duty;
    }

    private function dutyDisplay(): string
    {
        if ($this->kind->isAbsence()) {
            if ($this->shiftType !== null && $this->shiftType->is_active) {
                return $this->shiftType->code;
            }

            return strtoupper($this->kind->value);
        }

        if ($this->shiftType !== null && $this->shiftType->is_active) {
            return $this->shiftType->code;
        }

        return ShiftType::formatTime($this->start_time).'-'.ShiftType::formatTime($this->end_time);
    }
}
