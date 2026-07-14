<?php

namespace App\Actions\Issues;

use App\Models\Issue;
use App\Models\IssueUpdate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use App\Support\IssuePhotoStorage;
use Illuminate\Http\UploadedFile;

/**
 * Beheerder voegt een notitie toe aan een melding (optioneel met foto's).
 */
class CreateIssueUpdateAction
{
    public function __construct(
        private IssuePhotoStorage $storage,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(Issue $issue, User $user, string $description, array $photos = []): IssueUpdate
    {
        $description = trim($description);

        $update = $issue->updates()->create([
            'user_id' => $user->id,
            'kind' => 'note',
            'description' => $description,
        ]);

        $validPhotos = array_values(array_filter($photos, fn ($photo) => $photo instanceof UploadedFile));
        if ($validPhotos !== []) {
            Tenant::query()->findOrFail($issue->tenant_id)->assertCanAddPhotos(count($validPhotos));
        }

        foreach ($validPhotos as $photo) {
            $issue->photos()->create([
                'issue_update_id' => $update->id,
                'path' => $this->storage->storePrecompressedCopy($photo),
            ]);
        }

        $this->audit->record(
            userId: (int) $user->id,
            tenantId: (int) $issue->tenant_id,
            action: 'issue.update_added',
            modelType: Issue::class,
            modelId: (int) $issue->id,
            payload: ['issue_update_id' => $update->id],
        );

        return $update;
    }
}
