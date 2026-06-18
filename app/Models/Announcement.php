<?php

namespace App\Models;

use App\Enums\AnnouncementTranslationStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Translation\LocaleSupport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'unit_id',
        'category_id',
        'description',
        'original_language',
        'is_active',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(AnnouncementTranslation::class);
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

        $row = $this->findCompletedTranslation($locale);

        if ($row instanceof AnnouncementTranslation && filled($row->description)) {
            return (string) $row->description;
        }

        return $description;
    }

    public function descriptionMissingForDisplayLocale(string $locale): bool
    {
        $locale = LocaleSupport::normalize($locale);

        return $locale !== $this->normalizedOriginalLanguage()
            && ! $this->hasCompletedTranslationFor($locale);
    }

    public function descriptionForDisplayLocale(string $locale): string
    {
        $locale = LocaleSupport::normalize($locale);

        if ($locale === $this->normalizedOriginalLanguage()) {
            return (string) $this->description;
        }

        $row = $this->findCompletedTranslation($locale);

        if ($row instanceof AnnouncementTranslation && filled($row->description)) {
            return (string) $row->description;
        }

        return __('issues.show.description_not_translated', [], $locale);
    }

    public function hasCompletedTranslationFor(string $locale): bool
    {
        $locale = LocaleSupport::normalize($locale);

        if ($locale === $this->normalizedOriginalLanguage()) {
            return true;
        }

        $row = $this->findCompletedTranslation($locale);

        return $row instanceof AnnouncementTranslation && filled($row->description);
    }

    private function findCompletedTranslation(string $locale): ?AnnouncementTranslation
    {
        $locale = LocaleSupport::normalize($locale);

        if ($this->relationLoaded('translations')) {
            $row = $this->translations->first(
                fn (AnnouncementTranslation $translation) => $translation->locale === $locale
                    && $translation->status === AnnouncementTranslationStatus::Completed
                    && filled($translation->description),
            );

            return $row instanceof AnnouncementTranslation ? $row : null;
        }

        return $this->translations()
            ->where('locale', $locale)
            ->where('status', AnnouncementTranslationStatus::Completed)
            ->whereNotNull('description')
            ->first();
    }
}
