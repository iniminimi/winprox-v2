<?php

namespace App\Actions\Public;

use App\Actions\Issues\CreateIssueAction;
use App\Enums\IssueSource;
use App\Models\Issue;
use App\Models\Location;
use App\Support\IssuePhotoStorage;
use Illuminate\Http\UploadedFile;

/**
 * Publieke locatie-QR: melding zonder unit (location_id, unit_id null).
 */
class SubmitLocationReportAction
{
    public function __construct(
        private CreateIssueAction $createIssue,
        private IssuePhotoStorage $storage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(Location $location, array $data, array $photos = []): Issue
    {
        $issue = $this->createIssue->handle([
            'location_id' => $location->id,
            'unit_id' => null,
            'reporter_name' => $data['reporter_name'] ?? null,
            'reporter_contact' => $data['reporter_contact'] ?? null,
            'description' => $data['description'],
            'source' => IssueSource::QrLocation->value,
        ], []);

        foreach ($photos as $photo) {
            if ($photo instanceof UploadedFile) {
                $issue->photos()->create([
                    'path' => $this->storage->storePrecompressedCopy($photo),
                ]);
            }
        }

        return $issue;
    }
}
