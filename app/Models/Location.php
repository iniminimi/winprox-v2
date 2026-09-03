<?php

namespace App\Models;

use App\Enums\LocationTranslationStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Translation\LocaleSupport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'original_language',
        'address',
        'street',
        'house_number',
        'postal_code',
        'city',
        'country_code',
        'notes',
        'contractual_relationship_reference',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (Location $location) {
            if (empty($location->country_code)) {
                $location->country_code = 'BE';
            }
        });
    }

    public function formattedAddress(): string
    {
        $parts = array_filter([
            trim(trim((string) $this->street).' '.trim((string) $this->house_number)),
            trim(trim((string) $this->postal_code).' '.trim((string) $this->city)),
        ]);

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return (string) ($this->address ?? '');
    }

    public function hasCompleteAddress(): bool
    {
        return trim($this->formattedAddress()) !== '';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'location_user')->withTimestamps();
    }

    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(Worker::class, 'location_worker')->withTimestamps();
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function bulkBatches(): HasMany
    {
        return $this->hasMany(UnitBulkBatch::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LocationTranslation::class);
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

        if ($row instanceof LocationTranslation && filled($row->name)) {
            return (string) $row->name;
        }

        return $name;
    }

    private function findCompletedTranslation(string $locale): ?LocationTranslation
    {
        $locale = LocaleSupport::normalize($locale);

        if ($this->relationLoaded('translations')) {
            $row = $this->translations->first(
                fn (LocationTranslation $translation) => $translation->locale === $locale
                    && $translation->status === LocationTranslationStatus::Completed,
            );

            return $row instanceof LocationTranslation ? $row : null;
        }

        return $this->translations()
            ->where('locale', $locale)
            ->where('status', LocationTranslationStatus::Completed)
            ->first();
    }
}
