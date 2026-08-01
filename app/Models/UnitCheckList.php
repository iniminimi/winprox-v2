<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UnitCheckListTranslationStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Translation\LocaleSupport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitCheckList extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'internal_team_id',
        'name',
        'original_language',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(UnitCheckListItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function internalTeam(): BelongsTo
    {
        return $this->belongsTo(InternalTeam::class, 'internal_team_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(UnitCheckListTranslation::class);
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

        if ($row instanceof UnitCheckListTranslation && filled($row->name)) {
            return (string) $row->name;
        }

        return $name;
    }

    /**
     * Checklistpunten in de brontaal — de waarden die in `unit_checks.checklist_items` staan.
     *
     * @return list<string>
     */
    public function sourceItemLabels(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        $labels = [];
        foreach ($items as $item) {
            $label = trim((string) $item->label);
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    public function localizedItemLabel(string $sourceLabel, ?string $locale = null): string
    {
        $locale = LocaleSupport::normalize($locale ?? app()->getLocale());

        if ($locale === $this->normalizedOriginalLanguage()) {
            return $sourceLabel;
        }

        $row = $this->findCompletedTranslation($locale);

        if (! $row instanceof UnitCheckListTranslation || ! is_array($row->items)) {
            return $sourceLabel;
        }

        $index = array_search(trim($sourceLabel), $this->sourceItemLabels(), true);

        if ($index === false) {
            return $sourceLabel;
        }

        $translated = $row->items[$index] ?? null;

        return filled($translated) ? (string) $translated : $sourceLabel;
    }

    private function findCompletedTranslation(string $locale): ?UnitCheckListTranslation
    {
        if ($this->relationLoaded('translations')) {
            return $this->translations
                ->first(fn (UnitCheckListTranslation $row): bool => $row->locale === $locale
                    && $row->status === UnitCheckListTranslationStatus::Completed);
        }

        return $this->translations()
            ->where('locale', $locale)
            ->where('status', UnitCheckListTranslationStatus::Completed)
            ->first();
    }
}
