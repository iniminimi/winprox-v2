<?php

namespace App\Actions\Public;

use App\Actions\Issues\CreateIssueAction;
use App\Models\Issue;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\IssuePhotoStorage;
use Illuminate\Http\UploadedFile;

/**
 * Publieke QR-melding: maakt een (niet-goedgekeurde) melding voor een unit, maakt
 * automatisch een taak voor het standaardteam van de unit (port van FacilityQrIntake)
 * en bewaart de meegestuurde, reeds gecomprimeerde foto's. Geen (actief) team =>
 * geen taak, dus de melding blijft "Nieuw". Altijd ongekeurd (moderatie, §7.1).
 */
class SubmitReportAction
{
    public function __construct(
        private CreateIssueAction $createIssue,
        private IssuePhotoStorage $storage,
        private AssertPublicReportRateLimitAction $assertRateLimit,
        private RecordPublicReportRateLimitAction $recordRateLimit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(
        Unit $unit,
        array $data,
        array $photos = [],
        ?Worker $fieldWorker = null,
        ?string $clientIp = null,
    ): Issue {
        if (! $unit->public_reports_enabled && $fieldWorker === null) {
            throw new \InvalidArgumentException('public_reports_disabled');
        }

        $applyRateLimit = $fieldWorker === null && $clientIp !== null && trim($clientIp) !== '';

        if ($applyRateLimit) {
            $this->assertRateLimit->handle((int) $unit->tenant_id, (int) $unit->id, $clientIp);
        }

        $unit->loadMissing('category.teams');
        $teamIds = [];
        if ($unit->category !== null) {
            $team = $unit->category->teams()->first();
            if ($team !== null && $team->is_active) {
                $teamIds = [$team->id];
            }
        }

        $reporterName = $data['reporter_name'] ?? null;
        $reporterContact = $data['reporter_contact'] ?? null;

        if ($fieldWorker !== null) {
            $workerName = trim($fieldWorker->displayName());
            if ($workerName !== '') {
                $reporterName = $workerName;
            }
            $workerEmail = trim((string) ($fieldWorker->email ?? ''));
            if ($workerEmail !== '') {
                $reporterContact = $workerEmail;
            }
        }

        $issue = $this->createIssue->handle([
            'location_id' => $unit->location_id,
            'unit_id' => $unit->id,
            'reporter_name' => $reporterName,
            'reporter_contact' => $reporterContact,
            'description' => $data['description'],
            'source' => 'qr',
            'original_language' => $data['original_language'] ?? null,
        ], $teamIds);

        $validPhotos = array_values(array_filter($photos, fn ($photo) => $photo instanceof UploadedFile));
        if ($validPhotos !== []) {
            Tenant::query()->findOrFail($unit->tenant_id)->assertCanAddPhotos(count($validPhotos));
        }

        foreach ($validPhotos as $photo) {
            if ($photo instanceof UploadedFile) {
                $issue->photos()->create([
                    'path' => $this->storage->storePrecompressedCopy($photo),
                ]);
            }
        }

        if ($applyRateLimit) {
            $this->recordRateLimit->handle((int) $unit->tenant_id, (int) $unit->id, $clientIp);
        }

        return $issue;
    }
}
