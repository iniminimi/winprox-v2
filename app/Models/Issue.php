<?php

namespace App\Models;

use App\Enums\IssueSource;
use App\Enums\IssueTranslationStatus;
use App\Enums\RecurrenceIntervalUnit;
use App\Enums\TaskStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Translation\LocaleSupport;
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
        'original_language',
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

    public function translations(): HasMany
    {
        return $this->hasMany(IssueTranslation::class);
    }

    public function normalizedOriginalLanguage(): string
    {
        return LocaleSupport::normalize($this->original_language);
    }

    public function localizedDescription(?string $locale = null): string
    {
        $locale = LocaleSupport::normalize($locale ?? app()->getLocale());
        $description = (string) $this->description;

        if ($locale === $this->normalizedOriginalLanguage()) {
            return $description;
        }

        $row = $this->relationLoaded('translations')
            ? $this->translations->first(
                fn (IssueTranslation $translation) => $translation->locale === $locale
                    && $translation->status === IssueTranslationStatus::Completed
                    && filled($translation->text),
            )
            : $this->translations()
                ->where('locale', $locale)
                ->where('status', IssueTranslationStatus::Completed)
                ->whereNotNull('text')
                ->first();

        if ($row instanceof IssueTranslation && filled($row->text)) {
            return (string) $row->text;
        }

        return $description;
    }

    /**
     * @return array<string, string>
     */
    public function completedTranslationMap(): array
    {
        $rows = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->where('status', IssueTranslationStatus::Completed)->get();

        $map = [];
        foreach ($rows as $row) {
            if ($row->status === IssueTranslationStatus::Completed && filled($row->text)) {
                $map[$row->locale] = (string) $row->text;
            }
        }

        return $map;
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
