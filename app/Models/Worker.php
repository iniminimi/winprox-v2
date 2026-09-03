<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Worker extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'internal_team_id',
        'first_name',
        'last_name',
        'field_icon_slug',
        'field_icon_failed_attempts',
        'field_icon_locked_at',
        'is_active',
        'is_teamleader',
        'is_external',
        'company_name',
        'import_batch_id',
        'email',
        'phone',
        'ssin',
        'photo_path',
    ];

    protected $casts = [
        'field_icon_failed_attempts' => 'integer',
        'field_icon_locked_at' => 'datetime',
        'is_active' => 'boolean',
        'is_teamleader' => 'boolean',
        'is_external' => 'boolean',
        'ssin' => 'encrypted',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(InternalTeam::class, 'internal_team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_worker')->withTimestamps();
    }

    public function devices(): HasMany
    {
        return $this->hasMany(WorkerDevice::class);
    }

    public function displayName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function photoPublicUrl(): ?string
    {
        $path = $this->photo_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function initialsLetter(): string
    {
        $name = trim((string) $this->first_name);

        return mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1));
    }

    public function clocksAllLocations(): bool
    {
        $team = $this->relationLoaded('team') ? $this->team : $this->team()->first();

        return $team !== null && (bool) $team->clocks_all_locations;
    }

    /** null locationId op clock point = legacy tenant-breed. */
    public function canClockAt(?int $locationId): bool
    {
        if ($this->clocksAllLocations()) {
            return true;
        }

        if ($locationId === null) {
            return true;
        }

        $assigned = $this->relationLoaded('locations')
            ? $this->locations->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $this->locations()->pluck('locations.id')->map(fn ($id) => (int) $id)->all();

        if ($assigned === []) {
            return true;
        }

        return in_array($locationId, $assigned, true);
    }
}
