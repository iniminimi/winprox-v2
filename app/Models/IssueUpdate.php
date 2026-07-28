<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IssueUpdate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'issue_id', 'task_id', 'user_id', 'worker_id', 'kind', 'description'];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(IssuePhoto::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
