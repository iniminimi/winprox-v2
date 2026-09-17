<?php

namespace App\Actions\Portal;

use App\Actions\Public\SubmitReportAction;
use App\Actions\Tasks\CompleteTaskAction;
use App\Actions\Tasks\RoundTaskCompletionAction;
use App\Actions\Tasks\StartTaskAction;
use App\Actions\Units\RecordUnitCheckAndApplyTasksAction;
use App\Data\Portal\FieldSyncResult;
use App\Data\Units\RecordUnitCheckData;
use App\Enums\FieldSyncType;
use App\Enums\TaskStatus;
use App\Enums\UnitCheckResult;
use App\Enums\UnitCheckSource;
use App\Exceptions\Portal\FieldSyncException;
use App\Http\Requests\Esg\RecordEsgMeasurementRequest;
use App\Http\Requests\Public\CompletePortalTaskRequest;
use App\Http\Requests\Public\ReportIssueRequest;
use App\Http\Requests\Units\RecordUnitCheckRequest;
use App\Models\FieldSyncReceipt;
use App\Models\Task;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use App\Support\Portal\PortalAccess;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Tasks\TaskIssueApproval;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Eén veld-mutatie uit de outbox: zelfde regels als UnitPortal, bestaande Actions.
 */
class SyncFieldOutboxItemAction
{
    public function __construct(
        private StartTaskAction $startTask,
        private CompleteTaskAction $completeTask,
        private SubmitReportAction $submitReport,
        private RecordUnitCheckAndApplyTasksAction $recordCheck,
        private RoundTaskCompletionAction $roundCompletion,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(
        Worker $worker,
        string $clientId,
        FieldSyncType $type,
        Unit $unit,
        array $payload = [],
        array $photos = [],
    ): FieldSyncResult {
        $this->assertWorkerMayAct($worker, $unit);

        $tenantId = (int) $unit->tenant_id;

        return DB::transaction(function () use ($worker, $clientId, $type, $unit, $payload, $photos, $tenantId) {
            $existing = FieldSyncReceipt::query()
                ->where('worker_id', $worker->id)
                ->where('client_id', $clientId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return FieldSyncResult::fromReceipt($existing, replayed: true);
            }

            try {
                $result = $this->execute($worker, $type, $unit, $payload, $photos);
            } catch (FieldSyncException $exception) {
                if (! $exception->storeReceipt) {
                    throw $exception;
                }

                $receipt = $this->storeReceipt(
                    tenantId: $tenantId,
                    worker: $worker,
                    clientId: $clientId,
                    type: $type,
                    unit: $unit,
                    status: FieldSyncReceipt::STATUS_FAILED,
                    httpStatus: $exception->status,
                    result: [
                        'error' => $exception->getMessage(),
                        'errors' => $exception->errors,
                    ],
                );

                return FieldSyncResult::fromReceipt($receipt, replayed: false);
            } catch (ValidationException $exception) {
                $receipt = $this->storeReceipt(
                    tenantId: $tenantId,
                    worker: $worker,
                    clientId: $clientId,
                    type: $type,
                    unit: $unit,
                    status: FieldSyncReceipt::STATUS_FAILED,
                    httpStatus: 422,
                    result: [
                        'error' => __('portal.field_sync.error'),
                        'errors' => $exception->errors(),
                    ],
                );

                return FieldSyncResult::fromReceipt($receipt, replayed: false);
            }

            $receipt = $this->storeReceipt(
                tenantId: $tenantId,
                worker: $worker,
                clientId: $clientId,
                type: $type,
                unit: $unit,
                status: FieldSyncReceipt::STATUS_SUCCESS,
                httpStatus: 200,
                result: $result,
            );

            $this->audit->record(
                userId: null,
                tenantId: $tenantId,
                action: 'field_sync.'.$type->value,
                modelType: FieldSyncReceipt::class,
                modelId: $receipt->id,
                payload: [
                    'client_id' => $clientId,
                    'type' => $type->value,
                    'unit_id' => $unit->id,
                    'outcome' => $result['outcome'] ?? null,
                ],
            );

            return FieldSyncResult::fromReceipt($receipt, replayed: false);
        });
    }

    private function assertWorkerMayAct(Worker $worker, Unit $unit): void
    {
        if (! $worker->is_active) {
            throw new FieldSyncException(__('portal.worker.errors.no_permission'), 403, storeReceipt: false);
        }

        if ((int) $worker->tenant_id !== (int) $unit->tenant_id) {
            throw new FieldSyncException(__('portal.worker.errors.no_permission'), 403, storeReceipt: false);
        }

        if (! WorkerDeviceSession::workerCanActOnUnit($worker, $unit)) {
            throw new FieldSyncException(__('portal.worker.errors.no_permission'), 403, storeReceipt: false);
        }

        if (PortalAccess::unitPortalInactiveReasonKey($unit) !== null) {
            throw new FieldSyncException(__('portal.inactive.title'), 403, storeReceipt: false);
        }

        $team = $worker->team;
        if ($team === null || ! $team->is_active) {
            throw new FieldSyncException(__('portal.worker.errors.no_permission'), 403, storeReceipt: false);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile>  $photos
     * @return array<string, mixed>
     */
    private function execute(
        Worker $worker,
        FieldSyncType $type,
        Unit $unit,
        array $payload,
        array $photos,
    ): array {
        return match ($type) {
            FieldSyncType::TaskStart => $this->startTask($worker, $unit, $payload),
            FieldSyncType::TaskComplete => $this->completeTask($worker, $unit, $payload, $photos),
            FieldSyncType::IssueCreate => $this->createIssue($worker, $unit, $payload, $photos),
            FieldSyncType::UnitCheck => $this->recordUnitCheck($worker, $unit, $payload),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function startTask(Worker $worker, Unit $unit, array $payload): array
    {
        $task = $this->findUnitTask($worker, $unit, (int) ($payload['task_id'] ?? 0));
        $before = $task->status;

        try {
            TaskIssueApproval::assertTaskMutable($task);
        } catch (ValidationException $exception) {
            throw new FieldSyncException(
                collect($exception->errors())->flatten()->first() ?: __('portal.worker.errors.no_permission'),
                422,
                $exception->errors(),
            );
        }

        if ($before === TaskStatus::Done || $before === TaskStatus::Closed) {
            return [
                'outcome' => 'already_completed',
                'task_id' => $task->id,
                'status' => $before->value,
            ];
        }

        if ($before === TaskStatus::InProgress || ! $task->canStart()) {
            return [
                'outcome' => 'already_started',
                'task_id' => $task->id,
                'status' => $task->status->value,
            ];
        }

        $started = $this->startTask->handle(
            $task,
            $worker,
            $this->clientTimestamp($payload),
        );

        return [
            'outcome' => 'started',
            'task_id' => $started->id,
            'status' => $started->status->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile>  $photos
     * @return array<string, mixed>
     */
    private function completeTask(Worker $worker, Unit $unit, array $payload, array $photos): array
    {
        $task = $this->findUnitTask($worker, $unit, (int) ($payload['task_id'] ?? 0));
        $task->loadMissing(['issue.esgIndicator']);

        try {
            TaskIssueApproval::assertTaskMutable($task);
        } catch (ValidationException $exception) {
            throw new FieldSyncException(
                collect($exception->errors())->flatten()->first() ?: __('portal.worker.errors.no_permission'),
                422,
                $exception->errors(),
            );
        }

        if ($task->status === TaskStatus::Done || $task->status === TaskStatus::Closed) {
            return [
                'outcome' => 'already_completed',
                'task_id' => $task->id,
                'status' => $task->status->value,
            ];
        }

        if (! $task->canComplete()) {
            throw new FieldSyncException(__('portal.worker.errors.no_permission'), 422);
        }

        if ($task->issue?->isInspectionRound() && ! $this->roundCompletion->isComplete($task)) {
            throw new FieldSyncException(__('portal.worker.errors.no_permission'), 422);
        }

        $esgIndicator = $task->issue?->esgIndicator;
        $esgType = $esgIndicator?->type;

        $input = [
            'completingNote' => $payload['note'] ?? '',
            'completingPhotos' => $photos,
            'completingEsgValueNumeric' => $payload['esg_value_numeric'] ?? null,
            'completingEsgValueBoolean' => $payload['esg_value_boolean'] ?? null,
            'completingEsgValueString' => $payload['esg_value_string'] ?? '',
            'completingEsgValueJson' => $payload['esg_value_json'] ?? '',
            'completingEsgValueMultiChoice' => $payload['esg_value_multi_choice'] ?? [],
            'completingRecordedAt' => $payload['recorded_at'] ?? ($payload['queued_at'] ?? now()->toIso8601String()),
        ];

        $validator = Validator::make(
            $input,
            CompletePortalTaskRequest::ruleSet($esgType),
            CompletePortalTaskRequest::validationMessages($esgType),
        );
        if ($esgType !== null) {
            RecordEsgMeasurementRequest::assertPortalRecordedAt((string) $input['completingRecordedAt'], $validator);
        }
        $validator->validate();

        $esgMeasurement = null;
        $clientTimestamp = $this->clientTimestamp($payload);
        if ($esgIndicator !== null) {
            $esgMeasurement = RecordEsgMeasurementRequest::portalToData(
                $task->id,
                $esgIndicator,
                (string) $input['completingRecordedAt'],
                $input,
            );
            $clientTimestamp = Carbon::parse((string) $input['completingRecordedAt']);
        }

        $completed = $this->completeTask->handle(
            $task,
            $worker,
            is_string($input['completingNote']) ? $input['completingNote'] : null,
            $photos,
            $clientTimestamp,
            $esgMeasurement,
        );

        return [
            'outcome' => 'completed',
            'task_id' => $completed->id,
            'status' => $completed->status->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile>  $photos
     * @return array<string, mixed>
     */
    private function createIssue(Worker $worker, Unit $unit, array $payload, array $photos): array
    {
        $input = [
            'description' => trim((string) ($payload['description'] ?? '')),
            'original_language' => $payload['original_language'] ?? null,
        ];

        Validator::make(
            [...$input, 'photos' => $photos],
            ReportIssueRequest::portalRules(false, false),
            ReportIssueRequest::validationMessages(),
        )->validate();

        $result = $this->submitReport->handle(
            $unit,
            ReportIssueRequest::issueDataFromInput($input),
            $photos,
            $worker,
            null,
        );

        return [
            'outcome' => 'created',
            'issue_id' => $result->issue?->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function recordUnitCheck(Worker $worker, Unit $unit, array $payload): array
    {
        if (! $unit->allowsUnitChecks()) {
            throw new FieldSyncException(__('portal.worker.errors.no_permission'), 422);
        }

        $checkedAt = (string) ($payload['checked_at'] ?? '');
        $input = [
            'checkResult' => $payload['result'] ?? '',
            'checkLatitude' => $payload['latitude'] ?? null,
            'checkLongitude' => $payload['longitude'] ?? null,
            'checkCheckedAt' => $checkedAt !== '' ? $checkedAt : now()->toIso8601String(),
            'checkChecklistItems' => $payload['checklist_items'] ?? [],
        ];

        $validator = Validator::make(
            $input,
            RecordUnitCheckRequest::portalRuleSet(),
            RecordUnitCheckRequest::portalValidationMessages(),
        );
        RecordUnitCheckRequest::assertPortalCheckedAt((string) $input['checkCheckedAt'], $validator);
        $validator->validate();

        $unit->loadMissing(['unitCheckList.items']);
        $result = UnitCheckResult::from((string) $input['checkResult']);
        $requiredLabels = $unit->unitCheckList?->is_active
            ? $unit->unitCheckList->items->pluck('label')->all()
            : [];
        $selectedLabels = array_values(array_unique(array_filter(
            array_map(static fn ($item) => is_string($item) ? trim($item) : '', is_array($input['checkChecklistItems']) ? $input['checkChecklistItems'] : []),
            static fn (string $label) => $label !== '',
        )));

        if ($result === UnitCheckResult::Ok && $requiredLabels !== []) {
            $missing = array_values(array_diff($requiredLabels, $selectedLabels));
            if ($missing !== []) {
                throw new FieldSyncException(
                    __('portal.unit_check.errors.checklist_incomplete'),
                    422,
                    ['checkChecklistItems' => [__('portal.unit_check.errors.checklist_incomplete')]],
                );
            }
            $selectedLabels = $requiredLabels;
        } elseif ($requiredLabels !== []) {
            $selectedLabels = array_values(array_intersect($requiredLabels, $selectedLabels));
        } else {
            $selectedLabels = [];
        }

        $checkResult = $this->recordCheck->handle(
            unit: $unit,
            data: new RecordUnitCheckData(
                result: $result,
                checkedAt: CarbonImmutable::parse((string) $input['checkCheckedAt']),
                source: UnitCheckSource::Portal,
                latitude: $input['checkLatitude'] !== null && $input['checkLatitude'] !== '' ? (float) $input['checkLatitude'] : null,
                longitude: $input['checkLongitude'] !== null && $input['checkLongitude'] !== '' ? (float) $input['checkLongitude'] : null,
                checklistItems: $selectedLabels === [] ? null : $selectedLabels,
            ),
            tenantId: (int) $unit->tenant_id,
            worker: $worker,
            timezone: (string) config('app.timezone'),
            existingReportDescription: (string) ($payload['existing_description'] ?? ''),
        );

        return [
            'outcome' => 'recorded',
            'check_id' => $checkResult->check->id,
            'result' => $result->value,
            'suggested_report_description' => $checkResult->suggestedReportDescription,
        ];
    }

    private function findUnitTask(Worker $worker, Unit $unit, int $taskId): Task
    {
        if ($taskId < 1 || $worker->internal_team_id === null) {
            throw new FieldSyncException(__('portal.worker.errors.no_permission'), 422);
        }

        $task = Task::query()
            ->where('internal_team_id', $worker->internal_team_id)
            ->whereHas('issue', fn ($q) => $q->belongsToUnit($unit))
            ->with(['issue.esgIndicator.translations', 'issue.roundStops', 'roundStopSkips'])
            ->find($taskId);

        if ($task === null) {
            throw new FieldSyncException(__('portal.worker.errors.no_permission'), 422);
        }

        return $task;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function clientTimestamp(array $payload): ?Carbon
    {
        $raw = $payload['queued_at'] ?? $payload['client_timestamp'] ?? null;
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function storeReceipt(
        int $tenantId,
        Worker $worker,
        string $clientId,
        FieldSyncType $type,
        Unit $unit,
        string $status,
        int $httpStatus,
        array $result,
    ): FieldSyncReceipt {
        return FieldSyncReceipt::query()->create([
            'tenant_id' => $tenantId,
            'worker_id' => $worker->id,
            'client_id' => $clientId,
            'type' => $type,
            'unit_id' => $unit->id,
            'status' => $status,
            'http_status' => $httpStatus,
            'result' => $result,
        ]);
    }
}
