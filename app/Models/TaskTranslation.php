<?php

namespace App\Models;

use App\Enums\TaskTranslationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTranslation extends Model
{
    protected $fillable = [
        'task_id',
        'locale',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => TaskTranslationStatus::class,
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
