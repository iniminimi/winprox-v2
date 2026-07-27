<?php

declare(strict_types=1);

namespace App\Actions\Iot;

use App\Actions\Communication\EnsureIssueTranslationSlotsAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Enums\IssueSource;
use App\Enums\TaskStatus;
use App\Events\Issues\IssueCreated;
use App\Models\Issue;
use App\Models\IotEvent;
use App\Models\IotRule;
use App\Support\Audit\AuditRecorder;
use App\Support\Validation\TextDescriptionLimits;
use App\Support\Translation\LocaleSupport;
use Illuminate\Database\Eloquent\Builder;

class CreateIotAlarmIssueAction
{
    public function __construct(
        private CreateTaskAction $createTask,
        private EnsureIssueTranslationSlotsAction $ensureIssueTranslationSlots,
        private AuditRecorder $audit,
    ) {}

    public function handle(IotRule $rule, IotEvent $event, ?float $value): ?Issue
    {
        $rule->loadMissing(['sensor.unit', 'sensor.location']);
        $sensor = $rule->sensor;

        if ($sensor === null) {
            return null;
        }

        if ($this->hasOpenIssueForRule((int) $rule->tenant_id, (int) $rule->id)) {
            $this->audit->record(
                userId: null,
                tenantId: (int) $rule->tenant_id,
                action: 'iot_alarm.skipped_duplicate',
                modelType: IotRule::class,
                modelId: (int) $rule->id,
                payload: [
                    'iot_event_id' => $event->id,
                    'iot_sensor_id' => $sensor->id,
                ],
            );

            return null;
        }

        $description = $this->buildDescription($rule, $sensor->name, $value);

        $issue = Issue::query()->create([
            'tenant_id' => $rule->tenant_id,
            'location_id' => $sensor->location_id,
            'unit_id' => $sensor->unit_id,
            'description' => $description,
            'original_language' => LocaleSupport::normalize(null),
            'source' => IssueSource::Iot,
            'reporter_name' => 'IoT Connect',
            'approved_at' => now(),
            'approved_by' => null,
        ]);

        event(new IssueCreated($issue));
        $this->ensureIssueTranslationSlots->handle($issue);

        $this->createTask->handle(
            issue: $issue,
            internalTeamId: $rule->internal_team_id !== null ? (int) $rule->internal_team_id : null,
            priority: $rule->priority,
            description: $description,
            duringIssueIntake: true,
        );

        $this->audit->record(
            userId: null,
            tenantId: (int) $rule->tenant_id,
            action: 'iot_alarm.issue_created',
            modelType: Issue::class,
            modelId: (int) $issue->id,
            payload: [
                'iot_event_id' => $event->id,
                'iot_rule_id' => $rule->id,
                'iot_sensor_id' => $sensor->id,
                'value' => $value,
            ],
        );

        return $issue->fresh();
    }

    private function hasOpenIssueForRule(int $tenantId, int $ruleId): bool
    {
        return IotEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('iot_rule_id', $ruleId)
            ->whereNotNull('issue_id')
            ->whereHas('issue.tasks', fn (Builder $query) => $query
                ->whereIn('status', TaskStatus::openValues()))
            ->exists();
    }

    private function buildDescription(IotRule $rule, string $sensorName, ?float $value): string
    {
        $text = trim($rule->description);
        if ($text === '') {
            $text = __('iot.alarm.default_description', [
                'sensor' => $sensorName,
                'value' => $value ?? '—',
                'threshold' => $rule->threshold,
            ]);
        }

        return mb_substr($text, 0, TextDescriptionLimits::MAX);
    }
}
