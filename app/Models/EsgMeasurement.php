<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EsgMeasurement extends Model
{
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'location_id',
        'task_id',
        'esg_indicator_id',
        'worker_id',
        'value_numeric',
        'value_boolean',
        'value_string',
        'value_json',
        'corrects_measurement_id',
        'recorded_at',
    ];

    protected $casts = [
        'value_numeric' => 'decimal:4',
        'value_boolean' => 'boolean',
        'value_json' => 'array',
        'recorded_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(EsgIndicator::class, 'esg_indicator_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function correctsMeasurement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_measurement_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'corrects_measurement_id');
    }
}
