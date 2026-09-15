<?php

namespace App\Models;

use App\Enums\ShiftTypeColor;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftType extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'code',
        'label',
        'start_time',
        'end_time',
        'break_minutes',
        'color',
        'is_active',
    ];

    protected $casts = [
        'break_minutes' => 'integer',
        'is_active' => 'boolean',
        'color' => ShiftTypeColor::class,
    ];

    public function plannedShifts(): HasMany
    {
        return $this->hasMany(PlannedShift::class);
    }

    public function startMinutes(): int
    {
        return self::timeToMinutes($this->start_time);
    }

    public function endMinutes(): int
    {
        return self::timeToMinutes($this->end_time);
    }

    public static function normalizeCode(string $code): string
    {
        return mb_strtoupper(trim($code), 'UTF-8');
    }

    public static function timeToMinutes(string $time): int
    {
        $parts = explode(':', substr($time, 0, 5));
        $hours = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);

        return ($hours * 60) + $minutes;
    }

    public static function formatTime(string $time): string
    {
        return substr($time, 0, 5);
    }
}
