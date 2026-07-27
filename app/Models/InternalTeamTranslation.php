<?php

namespace App\Models;

use App\Enums\InternalTeamTranslationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalTeamTranslation extends Model
{
    protected $fillable = [
        'internal_team_id',
        'locale',
        'name',
        'status',
    ];

    protected $casts = [
        'status' => InternalTeamTranslationStatus::class,
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(InternalTeam::class, 'internal_team_id');
    }
}
