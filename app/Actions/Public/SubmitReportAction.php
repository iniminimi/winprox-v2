<?php

namespace App\Actions\Public;

use App\Actions\Issues\CreateIssueAction;
use App\Data\Public\SubmitReportResult;
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
 *
 * Wanneer categorie én unit e-mailbevestiging eisen, wordt de melding vastgehouden
 * tot de melder de link in de mail klikt (geen Issue/taak/webhook tot dan).
 */
class SubmitReportAction
{
    public function __construct(
        private CreateIssueAction $createIssue,
        private IssuePhotoStorage $storage,
        private AssertPublicReportRateLimitAction $assertRateLimit,
        private RecordPublicReportRateLimitAction $recordRateLimit,
        private HoldQrReportForEmailVerificationAction $holdForEmail,
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
    ): SubmitReportResult {
        if (! $unit->public_reports_enabled && $fieldWorker === null) {
            throw new \InvalidArgumentException('public_reports_disabled');
        }

        $applyRateLimit = $fieldWorker === null && $clientIp !== null && trim($clientIp) !== '';

        if ($applyRateLimit) {
            $this->assertRateLimit->handle((int) $unit->tenant_id, (int) $unit->id, $clientIp);
        }

        $reporterName = $data['reporter_name'] ?? null;
        $reporterContact = $data['reporter_contact'] ?? null;

        // Reporter fields represent the "citizen" identity (anonymous QR / public reports).
        // When public reports are disabled for visitors, the signed-in worker becomes the
        // only actor and we attribute reporter_name to the worker (citizen fields are
        // otherwise kept null).
        if ($fieldWorker !== null && ! $unit->public_reports_enabled) {
            $workerName = trim($fieldWorker->displayName());
            if ($workerName !== '') {
                $reporterName = $workerName;
            }
            // For public-reports-disabled mode, unit portal does not require reporter_email.
            // Keep reporter_contact only when the worker has an email configured.
            $workerEmail = trim((string) ($fieldWorker->email ?? ''));
            if ($workerEmail !== '') {
                $reporterContact = $workerEmail;
            }
        }

        $payload = array_merge($data, [
            'reporter_name' => $reporterName,
            'reporter_contact' => $reporterContact,
        ]);

        if ($fieldWorker === null && $unit->requiresReporterEmailVerification()) {
            $this->holdForEmail->handle($unit, $payload, $photos);

            if ($applyRateLimit) {
                $this->recordRateLimit->handle((int) $unit->tenant_id, (int) $unit->id, $clientIp);
            }

            return new SubmitReportResult(issue: null, awaitingEmailVerification: true);
        }

        $issue = $this->createIssueImmediately($unit, $payload, $photos);

        if ($applyRateLimit) {
            $this->recordRateLimit->handle((int) $unit->tenant_id, (int) $unit->id, $clientIp);
        }

        return new SubmitReportResult(issue: $issue, awaitingEmailVerification: false);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    private function createIssueImmediately(Unit $unit, array $data, array $photos): Issue
    {
        $unit->loadMissing('category.teams');
        $teamIds = [];
        if ($unit->category !== null) {
            $team = $unit->category->teams()->first();
            if ($team !== null && $team->is_active) {
                $teamIds = [$team->id];
            }
        }

        $issue = $this->createIssue->handle([
            'location_id' => $unit->location_id,
            'unit_id' => $unit->id,
            'reporter_name' => $data['reporter_name'] ?? null,
            'reporter_contact' => $data['reporter_contact'] ?? null,
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

        return $issue;
    }
}
