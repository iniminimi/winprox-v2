<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitCheckList extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'internal_team_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(UnitCheckListItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function internalTeam(): BelongsTo
    {
        return $this->belongsTo(InternalTeam::class, 'internal_team_id');
    }
}
