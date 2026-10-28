<?php

namespace App\Models;

use App\Enums\RosterRestorePointSource;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class RosterRestorePoint extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'actor_user_id',
        'source',
        'scope',
        'shifts',
    ];

    protected $casts = [
        'source' => RosterRestorePointSource::class,
        'scope' => 'array',
        'shifts' => 'array',
    ];
}
