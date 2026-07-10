<?php

namespace App\Models;

use App\Enums\BreakType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkBreak extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'work_shift_id',
        'started_at',
        'ended_at',
        'break_type',
    ];

    protected $casts = [
        'break_type' => BreakType::class,
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function durationMinutes(): int
    {
        $end = $this->ended_at ?? now();

        return max(0, (int) $this->started_at->diffInMinutes($end));
    }
}
