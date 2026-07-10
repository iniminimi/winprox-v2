<?php

namespace App\Actions\Communication;

use App\Enums\EsgIndicatorTranslationStatus;
use App\Models\EsgIndicator;
use App\Models\EsgIndicatorTranslation;
use App\Support\Audit\AuditRecorder;

class InvalidateEsgIndicatorTranslationsOnSourceChangeAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  list<string>  $previousOptions
     */
    public function handle(
        EsgIndicator $indicator,
        string $previousName,
        array $previousOptions,
        ?int $actorUserId = null,
    ): void {
        $nameChanged = trim($previousName) !== trim((string) $indicator->name);
        $optionsChanged = $previousOptions !== $indicator->normalizedChoiceOptions();

        if (! $nameChanged && ! $optionsChanged) {
            return;
        }

        if (! $indicator->is_active) {
            return;
        }

        $source = $indicator->normalizedOriginalLanguage();

        $invalidated = EsgIndicatorTranslation::query()
            ->where('esg_indicator_id', $indicator->id)
            ->where('locale', '!=', $source)
            ->where(function ($query) {
                $query->where('status', '!=', EsgIndicatorTranslationStatus::Pending->value)
                    ->orWhereNotNull('name')
                    ->orWhereNotNull('options');
            })
            ->update([
                'name' => null,
                'options' => null,
                'status' => EsgIndicatorTranslationStatus::Pending->value,
            ]);

        if ($invalidated === 0) {
            return;
        }

        $this->audit->record(
            $actorUserId,
            (int) $indicator->tenant_id,
            'esg_indicator.translations_invalidated',
            EsgIndicator::class,
            (int) $indicator->id,
            [
                'esg_indicator_id' => $indicator->id,
                'rows' => $invalidated,
                'source_locale' => $source,
            ],
        );
    }
}
