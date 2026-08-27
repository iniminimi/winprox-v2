<?php

namespace App\Models;

use App\Enums\UnitTranslationStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Translation\LocaleSupport;
use App\Support\Translation\TranslationOutputGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'allow_unit_checks',
        'allow_unit_measurements',
        'require_reporter_contact',
        'require_reporter_email_verification',
        'background_photo_path',
        'unit_check_list_id',
        'external_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'public_reports_enabled' => 'boolean',
        'allow_reservations' => 'boolean',
        'allow_unit_checks' => 'boolean',
        'allow_unit_measurements' => 'boolean',
        'require_reporter_contact' => 'boolean',
        'require_reporter_email_verification' => 'boolean',
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

    public function unitCheckList(): BelongsTo
    {
        return $this->belongsTo(UnitCheckList::class);
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

    public function allowsUnitChecks(): bool
    {
        if (! (bool) $this->allow_unit_checks) {
            return false;
        }

        // Geen categorie: alleen de unit-vlag telt.
        if ($this->category_id === null) {
            return true;
        }

        $this->loadMissing('category');

        return (bool) $this->category?->allow_unit_checks;
    }

    public function allowsUnitMeasurements(): bool
    {
        if (! (bool) $this->allow_unit_measurements) {
            return false;
        }

        if ($this->category_id === null) {
            return true;
        }

        $this->loadMissing('category');

        return (bool) $this->category?->allow_unit_measurements;
    }

    public function measureFields(): BelongsToMany
    {
        return $this->belongsToMany(UnitMeasureField::class, 'unit_measure_field_unit')
            ->withTimestamps();
    }

    public function activeMeasureFields(): BelongsToMany
    {
        return $this->measureFields()->where('unit_measure_fields.is_active', true);
    }

    public function scopeWhereAllowsUnitChecks(Builder $query): Builder
    {
        return $query
            ->where('units.allow_unit_checks', true)
            ->where(function (Builder $inner): void {
                $inner->whereNull('units.category_id')
                    ->orWhereHas('category', function (Builder $category): void {
                        $category->where('allow_unit_checks', true);
                    });
            });
    }

    /**
     * Active units that can be inspection-round stops, grouped by location.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: int}
     */
    public static function groupedInspectionRoundStops(): array
    {
        $activeCount = static::query()->where('is_active', true)->count();
        $units = static::query()
            ->where('is_active', true)
            ->whereAllowsUnitChecks()
            ->with(['location', 'category', 'translations'])
            ->orderBy('name')
            ->get();
        $grouped = $units
            ->groupBy(fn (Unit $unit) => (int) $unit->location_id)
            ->sortBy(fn ($group) => mb_strtolower((string) ($group->first()?->location?->name
                ?: $group->first()?->location?->address
                ?: '')));

        return [$grouped, max(0, $activeCount - $units->count())];
    }

    public function requiresReporterContact(): bool
    {
        $this->loadMissing('category');

        return (bool) $this->category?->require_reporter_contact && $this->require_reporter_contact;
    }

    public function requiresReporterEmailVerification(): bool
    {
        $this->loadMissing('category');

        return (bool) $this->category?->require_reporter_email_verification
            && $this->require_reporter_email_verification;
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

    public function unitChecks(): HasMany
    {
        return $this->hasMany(UnitCheck::class);
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

        if (
            $row instanceof UnitTranslation
            && filled($row->name)
            && ! TranslationOutputGuard::isUnusable((string) $row->name, $name)
        ) {
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

        if (
            $row instanceof UnitTranslation
            && filled($row->description)
            && ! TranslationOutputGuard::isUnusable((string) $row->description, $description)
        ) {
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

        if (
            $row instanceof UnitTranslation
            && filled($row->name)
            && ! TranslationOutputGuard::isUnusable((string) $row->name, (string) $this->name)
        ) {
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

        if (
            $row instanceof UnitTranslation
            && filled($row->description)
            && ! TranslationOutputGuard::isUnusable((string) $row->description, $description)
        ) {
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

        return $row instanceof UnitTranslation
            && filled($row->name)
            && ! TranslationOutputGuard::isUnusable((string) $row->name, (string) $this->name);
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

        return $row instanceof UnitTranslation
            && filled($row->description)
            && ! TranslationOutputGuard::isUnusable(
                (string) $row->description,
                (string) $this->description,
            );
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
            if (
                filled($row->name)
                && ! TranslationOutputGuard::isUnusable((string) $row->name, (string) $this->name)
            ) {
                $entry['name'] = (string) $row->name;
            }
            if (
                filled($row->description)
                && ! TranslationOutputGuard::isUnusable(
                    (string) $row->description,
                    (string) ($this->description ?? ''),
                )
            ) {
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
