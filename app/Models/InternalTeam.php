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

    protected $fillable = ['tenant_id', 'name', 'original_language', 'sort_order', 'is_active', 'session_lifespan_hours'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
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
