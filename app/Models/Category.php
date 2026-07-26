<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'allow_gps_location',
        'is_reservable',
    ];

    protected $casts = [
        'allow_gps_location' => 'boolean',
        'is_reservable' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(InternalTeam::class, 'category_internal_team', 'category_id', 'internal_team_id')
            ->withTimestamps();
    }
}
