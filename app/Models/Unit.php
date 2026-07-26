<?php

namespace App\Models;

use App\Enums\UnitTranslationStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Translation\LocaleSupport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Unit extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'category_id',
        'bulk_batch_id',
        'import_batch_id',
        'name',
        'description',
        'original_language',
        'is_active',
        'public_reports_enabled',
        'allow_reservations',
        'background_photo_path',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'public_reports_enabled' => 'boolean',
        'allow_reservations' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Unit $unit) {
            // Willekeurige token per unit (niet afgeleid van naam); uniek in de database.
            $unit->qr_token = self::generateUniqueQrToken();
        });
    }

    public static function generateUniqueQrToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (static::withoutGlobalScopes()->where('qr_token', $token)->exists());

        return $token;
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function bulkBatch(): BelongsTo
    {
        return $this->belongsTo(UnitBulkBatch::class, 'bulk_batch_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isReservable(): bool
    {
        $this->loadMissing('category');

        return (bool) $this->category?->is_reservable && $this->allow_reservations;
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

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function qrLinkPhotos(): HasMany
    {
        return $this->hasMany(QrLinkPhoto::class);
    }

    public function gpsReports(): HasMany
    {
        return $this->hasMany(UnitGpsReport::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(UnitTranslation::class);
    }

    public function normalizedOriginalLanguage(): string
    {
        return LocaleSupport::normalize($this->original_language);
    }

    public function localizedName(?string $locale = null): string
    {
        $locale = LocaleSupport::normalize($locale ?? app()->getLocale());
        $name = (string) $this->name;

        if ($locale === $this->normalizedOriginalLanguage()) {
            return $name;
        }

        $row = $this->findCompletedTranslation($locale);

        if ($row instanceof UnitTranslation && filled($row->name)) {
            return (string) $row->name;
        }

        return $name;
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

        if ($row instanceof UnitTranslation && filled($row->description)) {
            return (string) $row->description;
        }

        return $description;
    }

    public function nameMissingForDisplayLocale(string $locale): bool
    {
        $locale = LocaleSupport::normalize($locale);

        return $locale !== $this->normalizedOriginalLanguage()
            && ! $this->hasCompletedNameFor($locale);
    }

    public function descriptionMissingForDisplayLocale(string $locale): bool
    {
        $locale = LocaleSupport::normalize($locale);

        if (! filled($this->description)) {
            return false;
        }

        return $locale !== $this->normalizedOriginalLanguage()
            && ! $this->hasCompletedDescriptionFor($locale);
    }

    public function nameForDisplayLocale(string $locale): string
    {
        $locale = LocaleSupport::normalize($locale);

        if ($locale === $this->normalizedOriginalLanguage()) {
            return (string) $this->name;
        }

        $row = $this->findCompletedTranslation($locale);

        if ($row instanceof UnitTranslation && filled($row->name)) {
            return (string) $row->name;
        }

        return __('issues.show.description_not_translated', [], $locale);
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

        if ($row instanceof UnitTranslation && filled($row->description)) {
            return (string) $row->description;
        }

        return __('issues.show.description_not_translated', [], $locale);
    }

    public function hasCompletedNameFor(string $locale): bool
    {
        $locale = LocaleSupport::normalize($locale);

        if ($locale === $this->normalizedOriginalLanguage()) {
            return true;
        }

        $row = $this->findCompletedTranslation($locale);

        return $row instanceof UnitTranslation && filled($row->name);
    }

    public function hasCompletedDescriptionFor(string $locale): bool
    {
        $locale = LocaleSupport::normalize($locale);

        if ($locale === $this->normalizedOriginalLanguage()) {
            return true;
        }

        if (! filled($this->description)) {
            return true;
        }

        $row = $this->findCompletedTranslation($locale);

        return $row instanceof UnitTranslation && filled($row->description);
    }

    /**
     * @return array<string, array{name: string, description: ?string}>
     */
    public function completedTranslationMap(): array
    {
        $rows = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->where('status', UnitTranslationStatus::Completed)->get();

        $map = [];
        foreach ($rows as $row) {
            if ($row->status !== UnitTranslationStatus::Completed) {
                continue;
            }

            $entry = [];
            if (filled($row->name)) {
                $entry['name'] = (string) $row->name;
            }
            if (filled($row->description)) {
                $entry['description'] = (string) $row->description;
            }

            if ($entry !== []) {
                $map[$row->locale] = $entry;
            }
        }

        return $map;
    }

    private function findCompletedTranslation(string $locale): ?UnitTranslation
    {
        $locale = LocaleSupport::normalize($locale);

        if ($this->relationLoaded('translations')) {
            $row = $this->translations->first(
                fn (UnitTranslation $translation) => $translation->locale === $locale
                    && $translation->status === UnitTranslationStatus::Completed,
            );

            return $row instanceof UnitTranslation ? $row : null;
        }

        return $this->translations()
            ->where('locale', $locale)
            ->where('status', UnitTranslationStatus::Completed)
            ->first();
    }

    public function latestGpsReport(): HasOne
    {
        return $this->hasOne(UnitGpsReport::class)->latestOfMany('reported_at');
    }

    public function backgroundPhotoPublicUrl(): ?string
    {
        $path = $this->background_photo_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function hasOpenIssues(): bool
    {
        return $this->issues()
            ->whereIn('status', \App\Enums\TaskStatus::openValues())
            ->exists();
    }

    public function hasGps(): bool
    {
        if ($this->relationLoaded('latestGpsReport')) {
            return $this->latestGpsReport !== null;
        }

        if (isset($this->gps_reports_exists)) {
            return (bool) $this->gps_reports_exists;
        }

        return $this->gpsReports()->exists();
    }

    public function googleMapsUrl(): ?string
    {
        $report = $this->relationLoaded('latestGpsReport')
            ? $this->latestGpsReport
            : $this->latestGpsReport()->first();

        return $report?->googleMapsUrl();
    }
}
