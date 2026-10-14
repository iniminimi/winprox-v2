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
        'presence_clock_point_id',
        'clock_out_clock_point_id',
        'status',
        'clock_in_at',
        'clock_in_client_at',
        'clock_in_source',
        'clock_in_device_id',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_out_at',
        'clock_out_client_at',
        'clock_out_source',
        'total_break_minutes',
        'location_hops',
    ];

    protected $casts = [
        'status' => WorkShiftStatus::class,
        'clock_in_source' => ClockSource::class,
        'clock_out_source' => ClockSource::class,
        'clock_in_at' => 'datetime',
        'clock_in_client_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'clock_out_client_at' => 'datetime',
        'clock_in_latitude' => 'float',
        'clock_in_longitude' => 'float',
        'location_hops' => 'array',
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

    public function presenceClockPoint(): BelongsTo
    {
        return $this->belongsTo(ClockPoint::class, 'presence_clock_point_id');
    }

    public function clockOutClockPoint(): BelongsTo
    {
        return $this->belongsTo(ClockPoint::class, 'clock_out_clock_point_id');
    }

    /** Clock point waar de uitvoerder nu als aanwezig telt (na eventuele hops). */
    public function currentClockPointId(): int
    {
        return (int) ($this->presence_clock_point_id ?? $this->clock_in_clock_point_id);
    }

    public function currentClockPoint(): ?ClockPoint
    {
        if ($this->relationLoaded('presenceClockPoint') && $this->presenceClockPoint !== null) {
            return $this->presenceClockPoint;
        }

        if ($this->presence_clock_point_id !== null) {
            return $this->presenceClockPoint()->first();
        }

        return $this->relationLoaded('clockInClockPoint')
            ? $this->clockInClockPoint
            : $this->clockInClockPoint()->first();
    }

    /**
     * Aanwezigheid op dezelfde plek als dit clock point: hetzelfde punt, of een
     * ander punt op dezelfde vestiging. Twee punten met een vestiging die gelijk
     * is tellen als één plek — een hop is tussen vestigingen, niet binnen.
     * Punten zonder vestiging vallen terug op de punt-vergelijking.
     */
    public function isPresentAtSamePlace(ClockPoint $clockPoint): bool
    {
        $presencePoint = $this->currentClockPoint();
        if ($presencePoint === null) {
            return false;
        }

        if ((int) $presencePoint->id === (int) $clockPoint->id) {
            return true;
        }

        return $presencePoint->location_id !== null
            && $clockPoint->location_id !== null
            && (int) $presencePoint->location_id === (int) $clockPoint->location_id;
    }

    /** @return list<array<string, mixed>> */
    public function locationHops(): array
    {
        return is_array($this->location_hops) ? $this->location_hops : [];
    }

    public function hasLocationHops(): bool
    {
        return $this->locationHops() !== [];
    }

    public function clockInDevice(): BelongsTo
    {
        return $this->belongsTo(WorkerDevice::class, 'clock_in_device_id');
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(WorkBreak::class);
    }

    public function taskLogs(): HasMany
    {
        return $this->hasMany(WorkShiftTaskLog::class);
    }

    public function openBreak(): HasOne
    {
        return $this->hasOne(WorkBreak::class)->whereNull('ended_at')->latestOfMany('started_at');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(WorkVisit::class);
    }

    public function openVisit(): HasOne
    {
        return $this->hasOne(WorkVisit::class)->ofMany(
            ['started_at' => 'max'],
            fn ($query) => $query->whereNull('ended_at'),
        );
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', WorkShiftStatus::Open);
    }

    /**
     * Gesloten dienst: vastgelegd totaal (inclusief toegepast teamminimum).
     * Open dienst: geklokte pauzes, ook vóór het uitklokken.
     */
    public function visibleBreakMinutes(): int
    {
        if ($this->clock_out_at !== null) {
            return (int) $this->total_break_minutes;
        }

        $this->loadMissing('breaks');

        return (int) $this->breaks->sum(fn (WorkBreak $break) => $break->durationMinutes());
    }

    public function netWorkMinutes(): int
    {
        $end = $this->clock_out_at ?? now();

        return max(0, (int) $this->clock_in_at->diffInMinutes($end) - $this->visibleBreakMinutes());
    }

    public function isManuallyClockedIn(): bool
    {
        return $this->clock_in_source === ClockSource::Admin;
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
