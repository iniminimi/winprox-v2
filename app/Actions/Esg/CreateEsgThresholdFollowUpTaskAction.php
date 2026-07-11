<?php

declare(strict_types=1);

namespace App\Actions\Esg;

use App\Actions\Communication\EnsureIssueTranslationSlotsAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Enums\IssueSource;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Events\Esg\EsgThresholdFollowUpCreated;
use App\Events\Issues\IssueCreated;
use App\Models\EsgMeasurement;
use App\Models\Issue;
use App\Models\Task;
use App\Support\Audit\AuditRecorder;
use App\Support\Esg\EsgMeasurementPresenter;
use App\Support\Translation\LocaleSupport;
use Illuminate\Database\Eloquent\Builder;

class CreateEsgThresholdFollowUpTaskAction
{
    public function __construct(
        private CreateTaskAction $createTask,
        private EnsureIssueTranslationSlotsAction $ensureIssueTranslationSlots,
        private AuditRecorder $audit,
    ) {}

    public function handle(EsgMeasurement $measurement, ?int $actorUserId = null): ?Task
    {
        $measurement->loadMissing(['indicator', 'location', 'unit', 'task']);

        if (! EsgMeasurementPresenter::isOutsideThresholds($measurement)) {
            return null;
        }

        $indicator = $measurement->indicator;
        if ($indicator === null || $measurement->unit_id === null) {
            return null;
        }

        if ($this->hasOpenFollowUp(
            (int) $measurement->tenant_id,
            (int) $indicator->id,
            (int) $measurement->unit_id,
        )) {
            $this->audit->record(
                userId: $actorUserId,
                tenantId: (int) $measurement->tenant_id,
                action: 'esg_threshold_follow_up.skipped_duplicate',
                modelType: EsgMeasurement::class,
                modelId: (int) $measurement->id,
                payload: [
                    'esg_indicator_id' => $indicator->id,
                    'unit_id' => $measurement->unit_id,
                ],
            );

            return null;
        }

        $displayValue = EsgMeasurementPresenter::displayValue($measurement);
        $issue = Issue::query()->create([
            'tenant_id' => $measurement->tenant_id,
            'location_id' => $measurement->location_id,
            'unit_id' => $measurement->unit_id,
            'esg_indicator_id' => $indicator->id,
            'description' => __('esg.threshold_follow_up.issue_description', [
                'indicator' => $indicator->localizedName(),
                'value' => $displayValue,
                'location' => $measurement->location?->localizedName() ?? '—',
                'unit' => $measurement->unit?->localizedName() ?? '—',
            ]),
            'original_language' => LocaleSupport::normalize($indicator->normalizedOriginalLanguage()),
            'source' => IssueSource::Manager,
            'approved_at' => now(),
            'approved_by' => $actorUserId,
        ]);

        event(new IssueCreated($issue, $actorUserId));
        $this->ensureIssueTranslationSlots->handle($issue);

        $task = $this->createTask->handle(
            issue: $issue,
            internalTeamId: $measurement->task?->internal_team_id,
            priority: TaskPriority::Prio2,
            description: __('esg.threshold_follow_up.task_description', [
                'indicator' => $indicator->localizedName(),
                'value' => $displayValue,
            ]),
            extra: [
                'tenant_id' => (int) $measurement->tenant_id,
                'esg_threshold_measurement_id' => $measurement->id,
            ],
        );

        event(new EsgThresholdFollowUpCreated($task, $measurement, $actorUserId));

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $measurement->tenant_id,
            action: 'esg_threshold_follow_up.created',
            modelType: Task::class,
            modelId: (int) $task->id,
            payload: [
                'task_id' => $task->id,
                'issue_id' => $issue->id,
                'esg_measurement_id' => $measurement->id,
                'esg_indicator_id' => $indicator->id,
                'unit_id' => $measurement->unit_id,
                'location_id' => $measurement->location_id,
                'internal_team_id' => $task->internal_team_id,
            ],
        );

        return $task;
    }

    private function hasOpenFollowUp(int $tenantId, int $indicatorId, int $unitId): bool
    {
        return Task::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('esg_threshold_measurement_id')
            ->whereIn('status', TaskStatus::openValues())
            ->whereHas('issue', fn (Builder $query) => $query
                ->where('esg_indicator_id', $indicatorId)
                ->where('unit_id', $unitId))
            ->exists();
    }
}
