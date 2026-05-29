<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'unit_id',
        'reporter_name',
        'reporter_contact',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(IssueUpdate::class);
    }

    /**
     * Leidt de meldingstatus af uit de onderliggende taken en slaat ze op.
     * Rollup-regels: zie WINPROX_RULES.md §4.2.
     */
    public function recalculateStatus(): TaskStatus
    {
        $statuses = $this->tasks()
            ->pluck('status')
            ->map(fn ($status) => $status instanceof TaskStatus ? $status->value : $status);

        $derived = match (true) {
            $statuses->isEmpty() => TaskStatus::New,
            $statuses->every(fn ($s) => $s === TaskStatus::Closed->value) => TaskStatus::Closed,
            $statuses->every(fn ($s) => in_array($s, [TaskStatus::Done->value, TaskStatus::Closed->value], true)) => TaskStatus::Done,
            $statuses->contains(TaskStatus::InProgress->value) => TaskStatus::InProgress,
            default => TaskStatus::New,
        };

        if ($this->status !== $derived) {
            $this->status = $derived;
            $this->save();
        }

        return $derived;
    }
}
