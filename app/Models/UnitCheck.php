<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UnitCheckResult;
use App\Enums\UnitCheckSource;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitCheck extends Model
{
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'location_id',
        'worker_id',
        'internal_team_id',
        'result',
        'source',
        'checked_at',
        'latitude',
        'longitude',
        'task_id',
        'issue_id',
        'checklist_items',
    ];

    protected $casts = [
        'result' => UnitCheckResult::class,
        'source' => UnitCheckSource::class,
        'checked_at' => 'datetime',
        'created_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'checklist_items' => 'array',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(InternalTeam::class, 'internal_team_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function hasGps(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function googleMapsUrl(): ?string
    {
        if (! $this->hasGps()) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.$this->latitude.','.$this->longitude;
    }
}
