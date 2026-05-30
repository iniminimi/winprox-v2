<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitBulkBatch extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'prefix',
        'scheme',
        'floors',
        'rooms_per_floor',
        'internal_team_id',
        'units_count',
    ];

    protected $casts = [
        'floors' => 'integer',
        'rooms_per_floor' => 'integer',
        'units_count' => 'integer',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function internalTeam(): BelongsTo
    {
        return $this->belongsTo(InternalTeam::class, 'internal_team_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'bulk_batch_id');
    }
}
