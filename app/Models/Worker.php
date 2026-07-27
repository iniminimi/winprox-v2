<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worker extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
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
    ];

    protected $casts = [
        'field_icon_failed_attempts' => 'integer',
        'field_icon_locked_at' => 'datetime',
        'is_active' => 'boolean',
        'is_teamleader' => 'boolean',
        'is_external' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(InternalTeam::class, 'internal_team_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(WorkerDevice::class);
    }

    public function displayName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
