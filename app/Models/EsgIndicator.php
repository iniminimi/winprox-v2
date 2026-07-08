<?php

namespace App\Models;

use App\Enums\EsgIndicatorType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EsgIndicator extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'unit_of_measure',
        'is_active',
        'thresholds',
    ];

    protected $casts = [
        'type' => EsgIndicatorType::class,
        'is_active' => 'boolean',
        'thresholds' => 'array',
    ];

    public function measurements(): HasMany
    {
        return $this->hasMany(EsgMeasurement::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }
}
