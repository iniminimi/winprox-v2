<?php

namespace App\Actions\Time;

use App\Enums\PresenceSubmissionStatus;
use App\Models\Location;
use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use App\Support\Rsz\RszPresenceRegistrationClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SubmitPresenceBatchAction
{
    public function __construct(
        private RszPresenceRegistrationClient $client,
        private AuditRecorder $audit,
    ) {}

    public function handle(PresenceSubmission $submission): PresenceSubmission
    {
        $submission = PresenceSubmission::query()->whereKey($submission->id)->firstOrFail();

        if ($submission->status !== PresenceSubmissionStatus::Pending) {
            return $submission;
        }

        $tenant = Tenant::query()->findOrFail($submission->tenant_id);
        $worker = Worker::query()->findOrFail($submission->worker_id);
        $location = $submission->location_id
            ? Location::query()->find($submission->location_id)
            : null;

        $validationError = $this->validationError($tenant, $worker, $location, $submission);
        if ($validationError !== null) {
            return $this->mark($submission, PresenceSubmissionStatus::Skipped, null, null, null, $validationError, []);
        }

        $delay = (int) config('rsz.max_submit_delay_seconds', 600);
        if ($submission->registration_at->lt(now()->subSeconds($delay))) {
            return $this->mark(
                $submission,
                PresenceSubmissionStatus::Skipped,
                null,
                null,
                null,
                'rsz_submit_too_late',
                [],
            );
        }

        $item = $this->buildItem($tenant, $worker, $location, $submission);

        try {
            $response = $this->client->registerInBulk($tenant, [$item]);
        } catch (RuntimeException $e) {
            return $this->mark(
                $submission,
                PresenceSubmissionStatus::Failed,
                null,
                null,
                null,
                $e->getMessage(),
                ['item' => $this->safeMeta($item)],
            );
        }

        $created = $this->firstCreated($response);
        if ($created === null) {
            $notCreated = $this->firstNotCreated($response);

            return $this->mark(
                $submission,
                PresenceSubmissionStatus::Failed,
                null,
                null,
                is_array($notCreated) ? $notCreated : null,
                'rsz_not_created',
                ['item' => $this->safeMeta($item), 'response' => $response],
            );
        }

        return $this->mark(
            $submission,
            PresenceSubmissionStatus::Submitted,
            isset($created['id']) ? (int) $created['id'] : null,
            isset($created['validity']) ? (string) $created['validity'] : 'pending',
            $created['remarks'] ?? [],
            null,
            ['item' => $this->safeMeta($item)],
        );
    }

    private function validationError(Tenant $tenant, Worker $worker, ?Location $location, PresenceSubmission $submission): ?string
    {
        $ssin = preg_replace('/\D+/', '', (string) $worker->ssin) ?? '';
        if (strlen($ssin) !== 11) {
            return 'ssin_missing_or_invalid';
        }

        $enterprise = preg_replace('/\D+/', '', (string) $tenant->enterprise_number) ?? '';
        $foreignVat = trim((string) $tenant->foreign_vat_number);
        if ($enterprise === '' && $foreignVat === '') {
            return 'employer_number_missing';
        }
        if ($enterprise !== '' && ! preg_match('/^[01]\d{9}$/', $enterprise)) {
            return 'enterprise_number_invalid';
        }

        $ddt = $location?->contractual_relationship_reference;
        if (! is_string($ddt) || ! preg_match('/^[A-HJ-NP-Z0-9]{13}$/', $ddt)) {
            return 'ddt_missing_or_invalid';
        }

        if ($location === null || ! $this->locationHasPlaceOfWork($location)) {
            return 'place_of_work_missing';
        }

        return null;
    }

    private function locationHasPlaceOfWork(Location $location): bool
    {
        if ($location->latitude !== null && $location->longitude !== null) {
            return true;
        }

        return filled($location->street)
            && filled($location->postal_code)
            && filled($location->city);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildItem(Tenant $tenant, Worker $worker, Location $location, PresenceSubmission $submission): array
    {
        $ssin = preg_replace('/\D+/', '', (string) $worker->ssin);
        $employer = [];
        $enterprise = preg_replace('/\D+/', '', (string) $tenant->enterprise_number) ?? '';
        if ($enterprise !== '') {
            $employer['enterpriseNumber'] = $enterprise;
        } else {
            $employer['foreignVatNumber'] = trim((string) $tenant->foreign_vat_number);
        }

        $placeOfWork = $this->placeOfWork($location);

        return [
            'registrationDate' => $submission->registration_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'ssin' => $ssin,
            'type' => $submission->presence_type->value,
            'employer' => $employer,
            'placeOfWork' => $placeOfWork,
            'contractualRelationshipReference' => $location->contractual_relationship_reference,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function placeOfWork(Location $location): array
    {
        if ($location->latitude !== null && $location->longitude !== null) {
            return [
                'coordinates' => [
                    'longitude' => (float) $location->longitude,
                    'latitude' => (float) $location->latitude,
                ],
            ];
        }

        return [
            'address' => [
                'postCode' => (string) $location->postal_code,
                'municipalityName' => (string) $location->city,
                'streetName' => (string) $location->street,
                'houseNumber' => (string) ($location->house_number ?? ''),
                'boxNumber' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    private function firstCreated(array $response): ?array
    {
        $rows = $response['items'] ?? $response;
        if (! is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $created = $row['createdPresenceRegistration'] ?? null;
            if (is_array($created)) {
                return $created;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    private function firstNotCreated(array $response): ?array
    {
        $rows = $response['items'] ?? $response;
        if (! is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $not = $row['notCreatedPresenceRegistration'] ?? null;
            if (is_array($not)) {
                return $not;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function safeMeta(array $item): array
    {
        $copy = $item;
        if (isset($copy['ssin']) && is_string($copy['ssin']) && strlen($copy['ssin']) === 11) {
            $copy['ssin'] = substr($copy['ssin'], 0, 3).'****'.substr($copy['ssin'], -2);
        }

        return $copy;
    }

    /**
     * @param  array<string, mixed>|null  $remarks
     * @param  array<string, mixed>  $meta
     */
    private function mark(
        PresenceSubmission $submission,
        PresenceSubmissionStatus $status,
        ?int $rszId,
        ?string $validity,
        ?array $remarks,
        ?string $error,
        array $meta,
    ): PresenceSubmission {
        return DB::transaction(function () use ($submission, $status, $rszId, $validity, $remarks, $error, $meta) {
            $locked = PresenceSubmission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'status' => $status,
                'rsz_id' => $rszId,
                'rsz_validity' => $validity,
                'remarks' => $remarks,
                'error_message' => $error,
                'request_meta' => $meta,
                'submitted_at' => $status === PresenceSubmissionStatus::Submitted ? Carbon::now() : $locked->submitted_at,
            ]);

            $fresh = $locked->fresh();

            $this->audit->record(
                userId: null,
                tenantId: (int) $fresh->tenant_id,
                action: 'presence_submission.'.$status->value,
                modelType: PresenceSubmission::class,
                modelId: (int) $fresh->id,
                payload: [
                    'source_event' => $fresh->source_event->value,
                    'presence_type' => $fresh->presence_type->value,
                    'rsz_id' => $fresh->rsz_id,
                    'error_message' => $fresh->error_message,
                ],
            );

            return $fresh;
        });
    }
}
