<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UnitMeasureFieldType;
use App\Enums\UnitMeasurementSource;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitMeasurement extends Model
{
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'location_id',
        'unit_measure_field_id',
        'worker_id',
        'user_id',
        'source',
        'value_numeric',
        'value_boolean',
        'value_string',
        'recorded_at',
        'created_at',
    ];

    protected $casts = [
        'source' => UnitMeasurementSource::class,
        'value_numeric' => 'float',
        'value_boolean' => 'boolean',
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

    public function field(): BelongsTo
    {
        return $this->belongsTo(UnitMeasureField::class, 'unit_measure_field_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayValue(): string
    {
        $this->loadMissing('field');
        $type = $this->field?->type;

        return match ($type) {
            UnitMeasureFieldType::Numeric => $this->formatNumeric(),
            UnitMeasureFieldType::Boolean => $this->value_boolean === null
                ? '—'
                : ($this->value_boolean ? __('unit_measurements.value.yes') : __('unit_measurements.value.no')),
            UnitMeasureFieldType::String, UnitMeasureFieldType::Choice => (string) ($this->value_string ?? '—'),
            default => '—',
        };
    }

    private function formatNumeric(): string
    {
        if ($this->value_numeric === null) {
            return '—';
        }

        $formatted = rtrim(rtrim(number_format((float) $this->value_numeric, 4, '.', ''), '0'), '.');
        $unit = $this->field?->unit_of_measure;

        return $unit ? $formatted.' '.$unit : $formatted;
    }
}
