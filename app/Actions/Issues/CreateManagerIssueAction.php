<?php

namespace App\Actions\Issues;

use App\Actions\Communication\EnsureIssueTranslationSlotsAction;
use App\Enums\IssueSource;
use App\Events\Issues\IssueCreated;
use App\Models\Issue;
use App\Models\Tenant;
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
        private SyncIssueRoundStopsAction $syncRoundStops,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(array $data, User $actor, array $photos = []): Issue
    {
        $recurring = RecurrenceSchedule::issueAttributesFromValidated($data);
        $roundStopIds = array_values(array_filter(array_map(
            'intval',
            $data['round_stop_unit_ids'] ?? [],
        )));
        $isRound = count($roundStopIds) >= 2;

        $issue = Issue::create([
            'location_id' => $isRound ? null : ($data['location_id'] ?? null),
            'unit_id' => $isRound ? null : ($data['unit_id'] ?? null),
            'esg_indicator_id' => $isRound ? null : self::resolveEsgIndicatorId($data),
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

        $validPhotos = array_values(array_filter($photos, fn ($photo) => $photo instanceof UploadedFile));
        if ($validPhotos !== []) {
            Tenant::query()->findOrFail($actor->tenant_id)->assertCanAddPhotos(count($validPhotos));
        }

        foreach ($validPhotos as $photo) {
            $issue->photos()->create([
                'path' => $this->storage->storePrecompressedCopy($photo),
            ]);
        }

        if ($isRound) {
            $issue = $this->syncRoundStops->handle($issue, $roundStopIds, $actor);
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
