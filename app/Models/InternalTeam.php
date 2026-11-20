<?php

namespace App\Models;

use App\Enums\InternalTeamTranslationStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Translation\LocaleSupport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternalTeam extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'original_language',
        'sort_order',
        'is_active',
        'clocks_all_locations',
        'required_break_minutes',
        'stale_shift_hours',
        'long_shift_hours',
        'session_lifespan_hours',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'clocks_all_locations' => 'boolean',
        'required_break_minutes' => 'integer',
        'stale_shift_hours' => 'integer',
        'long_shift_hours' => 'integer',
    ];

    /** Kalenderuren sinds inklok waarna de dienst automatisch wordt gesloten (1–24). */
    public function staleShiftHours(): int
    {
        return self::clampShiftHours(
            $this->stale_shift_hours,
            (int) config('time.stale_shift_hours', 16),
        );
    }

    /** Kalenderuren sinds inklok voor het alarm “lange shift” (1–24). */
    public function longShiftHours(): int
    {
        return self::clampShiftHours(
            $this->long_shift_hours,
            (int) config('time.long_shift_hours', 10),
        );
    }

    public static function clampShiftHours(mixed $value, int $fallback): int
    {
        $hours = is_numeric($value) ? (int) $value : $fallback;

        return max(1, min(24, $hours > 0 ? $hours : $fallback));
    }

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    public function unitCheckLists(): HasMany
    {
        return $this->hasMany(UnitCheckList::class);
    }

    public function activeWorkerCount(): int
    {
        return (int) $this->workers()->where('is_active', true)->count();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_internal_team', 'internal_team_id', 'category_id')
            ->withTimestamps();
    }

    public function translations(): HasMany
    {
        return $this->hasMany(InternalTeamTranslation::class);
    }

    public function normalizedOriginalLanguage(): string
    {
        return LocaleSupport::normalize($this->original_language);
    }

    public function localizedName(?string $locale = null): string
    {
        $locale = LocaleSupport::normalize($locale ?? app()->getLocale());
        $name = (string) $this->name;

        if ($name === '' || $locale === $this->normalizedOriginalLanguage()) {
            return $name;
        }

        $row = $this->findCompletedTranslation($locale);

        if ($row instanceof InternalTeamTranslation && filled($row->name)) {
            return (string) $row->name;
        }

        return $name;
    }

    private function findCompletedTranslation(string $locale): ?InternalTeamTranslation
    {
        $locale = LocaleSupport::normalize($locale);

        if ($this->relationLoaded('translations')) {
            $row = $this->translations->first(
                fn (InternalTeamTranslation $translation) => $translation->locale === $locale
                    && $translation->status === InternalTeamTranslationStatus::Completed,
            );

            return $row instanceof InternalTeamTranslation ? $row : null;
        }

        return $this->translations()
            ->where('locale', $locale)
            ->where('status', InternalTeamTranslationStatus::Completed)
            ->first();
    }
}
