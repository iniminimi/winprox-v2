<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskTranslationStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Translation\LocaleSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'description',
        'original_language',
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

    /** Taken van goedgekeurde meldingen (zichtbaar in beheer). */
    public function scopeForApprovedIssue(Builder $query): Builder
    {
        return $query->whereHas('issue', fn (Builder $issueQuery) => $issueQuery->whereNotNull('approved_at'));
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

    public function translations(): HasMany
    {
        return $this->hasMany(TaskTranslation::class);
    }

    public function normalizedOriginalLanguage(): string
    {
        return LocaleSupport::normalize($this->original_language);
    }

    public function localizedDescription(?string $locale = null): string
    {
        $locale = LocaleSupport::normalize($locale ?? app()->getLocale());
        $description = (string) ($this->description ?? '');

        if ($description === '') {
            return '';
        }

        if ($locale === $this->normalizedOriginalLanguage()) {
            return $description;
        }

        $row = $this->findCompletedTranslation($locale);

        if ($row instanceof TaskTranslation && filled($row->description)) {
            return (string) $row->description;
        }

        return $description;
    }

    public function displayDescription(?string $locale = null): string
    {
        if (filled(trim((string) ($this->description ?? '')))) {
            return $this->localizedDescription($locale);
        }

        return $this->issue?->localizedDescription($locale) ?? '';
    }

    public function hasCompletedTranslationFor(string $locale): bool
    {
        $locale = LocaleSupport::normalize($locale);

        if ($locale === $this->normalizedOriginalLanguage()) {
            return true;
        }

        if (! filled(trim((string) ($this->description ?? '')))) {
            return true;
        }

        $row = $this->findCompletedTranslation($locale);

        return $row instanceof TaskTranslation && filled($row->description);
    }

    public function descriptionMissingForDisplayLocale(string $locale): bool
    {
        $locale = LocaleSupport::normalize($locale);

        if (! filled(trim((string) ($this->description ?? '')))) {
            return false;
        }

        return $locale !== $this->normalizedOriginalLanguage()
            && ! $this->hasCompletedTranslationFor($locale);
    }

    public function descriptionForDisplayLocale(string $locale): string
    {
        $locale = LocaleSupport::normalize($locale);
        $description = (string) ($this->description ?? '');

        if ($description === '') {
            return '';
        }

        if ($locale === $this->normalizedOriginalLanguage()) {
            return $description;
        }

        $row = $this->findCompletedTranslation($locale);

        if ($row instanceof TaskTranslation && filled($row->description)) {
            return (string) $row->description;
        }

        return __('issues.show.description_not_translated', [], $locale);
    }

    private function findCompletedTranslation(string $locale): ?TaskTranslation
    {
        $locale = LocaleSupport::normalize($locale);

        if ($this->relationLoaded('translations')) {
            $row = $this->translations->first(
                fn (TaskTranslation $translation) => $translation->locale === $locale
                    && $translation->status === TaskTranslationStatus::Completed
                    && filled($translation->description),
            );

            return $row instanceof TaskTranslation ? $row : null;
        }

        return $this->translations()
            ->where('locale', $locale)
            ->where('status', TaskTranslationStatus::Completed)
            ->whereNotNull('description')
            ->first();
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
