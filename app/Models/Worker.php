<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Worker extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'internal_team_id', 'name'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(InternalTeam::class, 'internal_team_id');
    }
}
