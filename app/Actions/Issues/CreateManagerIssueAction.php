<?php

namespace App\Actions\Issues;

use App\Actions\Communication\EnsureIssueTranslationSlotsAction;
use App\Enums\IssueSource;
use App\Events\Issues\IssueCreated;
use App\Models\Issue;
use App\Models\User;
use App\Support\IssuePhotoStorage;
use App\Support\Recurrence\RecurrenceSchedule;
use App\Support\Translation\LocaleSupport;
use Illuminate\Http\UploadedFile;

/**
 * Stap 1 facility-flow: melding aanmaken door beheer (source=manager, direct goedgekeurd).
 */
class CreateManagerIssueAction
{
    public function __construct(
        private IssuePhotoStorage $storage,
        private EnsureIssueTranslationSlotsAction $ensureTranslationSlots,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(array $data, User $actor, array $photos = []): Issue
    {
        $recurring = RecurrenceSchedule::issueAttributesFromValidated($data);

        $issue = Issue::create([
            'location_id' => $data['location_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'esg_indicator_id' => self::resolveEsgIndicatorId($data),
            'description' => $data['description'],
            'original_language' => LocaleSupport::normalize(
                $data['original_language'] ?? $actor->locale ?? null,
            ),
            'source' => IssueSource::Manager,
            'reporter_name' => $actor->name,
            'approved_at' => now(),
            'approved_by' => $actor->id,
            ...$recurring,
        ]);

        event(new IssueCreated($issue));

        foreach (array_values(array_filter($photos, fn ($photo) => $photo instanceof UploadedFile)) as $photo) {
            $issue->photos()->create([
                'path' => $this->storage->storePrecompressedCopy($photo),
            ]);
        }

        $issue = $issue->fresh();
        $this->ensureTranslationSlots->handle($issue);

        return $issue;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function resolveEsgIndicatorId(array $data): ?int
    {
        if (! ($data['is_recurring'] ?? false)) {
            return null;
        }

        $indicatorId = $data['esg_indicator_id'] ?? null;

        return $indicatorId !== null && $indicatorId !== '' ? (int) $indicatorId : null;
    }
}
