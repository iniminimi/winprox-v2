<?php

namespace App\Models;

use App\Enums\IssueSource;
use App\Enums\RecurrenceIntervalUnit;
use App\Enums\TaskStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
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
        'source',
        'is_recurring',
        'recurrence_interval_value',
        'recurrence_interval_unit',
        'recurrence_lead_days',
        'recurrence_active',
        'recurrence_paused_at',
        'recurrence_next_due_at',
        'recurrence_last_task_created_at',
        'status',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'source' => IssueSource::class,
        'is_recurring' => 'boolean',
        'recurrence_interval_unit' => RecurrenceIntervalUnit::class,
        'recurrence_active' => 'boolean',
        'recurrence_paused_at' => 'datetime',
        'recurrence_next_due_at' => 'datetime',
        'recurrence_last_task_created_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Beginstatus Nieuw zodat een nét aangemaakte melding geen valse
     * "status_changed" afvuurt bij de eerste status-rollup (zie RecalculateIssueStatusAction).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => TaskStatus::New->value,
        'source' => IssueSource::Manager->value,
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

    public function photos(): HasMany
    {
        return $this->hasMany(IssuePhoto::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Tot goedkeuring blijven beschrijving + foto's geblurd (moderatie van
     * QR-inzendingen om compromitterende inhoud te voorkomen).
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Een gesloten melding mag geen taken of updates meer ontvangen.
     */
    public function isClosed(): bool
    {
        return $this->status === TaskStatus::Closed;
    }

    /** QR-meldingen die nog wachten op goedkeuring (niet afgewezen/gesloten). */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query
            ->whereNull('approved_at')
            ->where('status', '!=', TaskStatus::Closed);
    }

}
