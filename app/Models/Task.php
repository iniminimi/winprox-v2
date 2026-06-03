<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'issue_id',
        'internal_team_id',
        'status',
        'priority',
        'started_at',
        'completed_at',
        'note',
        'scheduled_for',
        'due_at',
        'is_recurring_cycle',
        'recurrence_issue_id',
        'cycle_number',
        'carryover_from_task_id',
        'not_executed_at',
        'late_by_days',
        'hold_started_at',
        'hold_total_minutes',
        'status_reason',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'priority' => TaskPriority::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'scheduled_for' => 'date',
        'due_at' => 'datetime',
        'is_recurring_cycle' => 'boolean',
        'not_executed_at' => 'datetime',
        'hold_started_at' => 'datetime',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(InternalTeam::class, 'internal_team_id');
    }

    public function recurrenceIssue(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'recurrence_issue_id');
    }

    public function carryoverFromTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'carryover_from_task_id');
    }

    /** Open op de werkvloer: nog Nieuw of In uitvoering. */
    public function isOpen(): bool
    {
        return in_array($this->status, [TaskStatus::New, TaskStatus::InProgress], true);
    }

    /** Mag gestart worden: nog Nieuw. */
    public function canStart(): bool
    {
        return $this->status === TaskStatus::New;
    }

    /** Mag afgehandeld worden: In uitvoering (of al gestart maar nog open). */
    public function canComplete(): bool
    {
        return $this->status === TaskStatus::InProgress
            || ($this->isOpen() && $this->started_at !== null);
    }
}
