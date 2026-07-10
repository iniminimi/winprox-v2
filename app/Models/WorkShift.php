<?php

namespace App\Models;

use App\Enums\ClockSource;
use App\Enums\WorkShiftStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkShift extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'worker_id',
        'internal_team_id',
        'clock_in_clock_point_id',
        'clock_out_clock_point_id',
        'status',
        'clock_in_at',
        'clock_in_client_at',
        'clock_in_source',
        'clock_in_device_id',
        'clock_out_at',
        'clock_out_client_at',
        'clock_out_source',
        'total_break_minutes',
    ];

    protected $casts = [
        'status' => WorkShiftStatus::class,
        'clock_in_source' => ClockSource::class,
        'clock_out_source' => ClockSource::class,
        'clock_in_at' => 'datetime',
        'clock_in_client_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'clock_out_client_at' => 'datetime',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(InternalTeam::class, 'internal_team_id');
    }

    public function clockInClockPoint(): BelongsTo
    {
        return $this->belongsTo(ClockPoint::class, 'clock_in_clock_point_id');
    }

    public function clockOutClockPoint(): BelongsTo
    {
        return $this->belongsTo(ClockPoint::class, 'clock_out_clock_point_id');
    }

    public function clockInDevice(): BelongsTo
    {
        return $this->belongsTo(WorkerDevice::class, 'clock_in_device_id');
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(WorkBreak::class);
    }

    public function openBreak(): HasOne
    {
        return $this->hasOne(WorkBreak::class)->whereNull('ended_at')->latestOfMany('started_at');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', WorkShiftStatus::Open);
    }

    public function netWorkMinutes(): int
    {
        if ($this->clock_out_at === null) {
            return max(0, (int) $this->clock_in_at->diffInMinutes(now()) - (int) $this->total_break_minutes);
        }

        return max(0, (int) $this->clock_in_at->diffInMinutes($this->clock_out_at) - (int) $this->total_break_minutes);
    }

    public function isOnBreak(): bool
    {
        if (! $this->status->isOpen()) {
            return false;
        }

        return $this->relationLoaded('openBreak')
            ? $this->openBreak !== null
            : $this->breaks()->whereNull('ended_at')->exists();
    }
}
