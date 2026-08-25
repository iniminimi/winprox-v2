<?php

namespace App\Models;

use App\Enums\CategoryTranslationStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Translation\LocaleSupport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'original_language',
        'allow_gps_location',
        'is_reservable',
        'allow_unit_checks',
        'require_reporter_contact',
        'require_reporter_email_verification',
    ];

    protected $casts = [
        'allow_gps_location' => 'boolean',
        'is_reservable' => 'boolean',
        'allow_unit_checks' => 'boolean',
        'require_reporter_contact' => 'boolean',
        'require_reporter_email_verification' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(InternalTeam::class, 'category_internal_team', 'category_id', 'internal_team_id')
            ->withTimestamps();
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
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

        if ($row instanceof CategoryTranslation && filled($row->name)) {
            return (string) $row->name;
        }

        return $name;
    }

    private function findCompletedTranslation(string $locale): ?CategoryTranslation
    {
        $locale = LocaleSupport::normalize($locale);

        if ($this->relationLoaded('translations')) {
            $row = $this->translations->first(
                fn (CategoryTranslation $translation) => $translation->locale === $locale
                    && $translation->status === CategoryTranslationStatus::Completed,
            );

            return $row instanceof CategoryTranslation ? $row : null;
        }

        return $this->translations()
            ->where('locale', $locale)
            ->where('status', CategoryTranslationStatus::Completed)
            ->first();
    }
}
