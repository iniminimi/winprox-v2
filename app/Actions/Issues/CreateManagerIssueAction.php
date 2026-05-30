<?php

namespace App\Actions\Issues;

use App\Enums\IssueSource;
use App\Events\Issues\IssueCreated;
use App\Models\Issue;
use App\Models\User;
use App\Support\IssuePhotoStorage;
use App\Support\Recurrence\RecurrenceSchedule;
use Illuminate\Http\UploadedFile;

/**
 * Stap 1 facility-flow: melding aanmaken door beheer (source=manager, direct goedgekeurd).
 */
class CreateManagerIssueAction
{
    public function __construct(private IssuePhotoStorage $storage) {}

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
            'description' => $data['description'],
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

        return $issue->fresh();
    }
}
