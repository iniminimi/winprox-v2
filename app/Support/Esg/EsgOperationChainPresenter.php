<?php

declare(strict_types=1);

namespace App\Support\Esg;

use App\Models\EsgMeasurement;
use App\Models\Task;

final class EsgOperationChainPresenter
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     detail: ?string,
     *     url: ?string,
     *     status_label: ?string,
     *     status_modifier: ?string,
     * }>
     */
    public static function stepsForMeasurement(EsgMeasurement $measurement): array
    {
        $steps = [[
            'key' => 'measurement',
            'label' => __('esg.chain.measurement'),
            'detail' => EsgMeasurementPresenter::displayValue($measurement),
            'url' => self::pointHistoryUrl($measurement),
            'status_label' => null,
            'status_modifier' => null,
        ]];

        if (EsgMeasurementPresenter::isOutsideThresholds($measurement)) {
            $steps[] = [
                'key' => 'alarm',
                'label' => __('esg.chain.alarm'),
                'detail' => __('esg.measurements.outside_thresholds'),
                'url' => null,
                'status_label' => null,
                'status_modifier' => 'progress',
            ];
        }

        if ($measurement->task_id !== null) {
            $steps[] = self::taskStep(
                key: 'measurement_task',
                label: __('esg.chain.measurement_task'),
                task: $measurement->relationLoaded('task') ? $measurement->task : null,
                taskId: (int) $measurement->task_id,
            );
        }

        $followUp = $measurement->relationLoaded('thresholdFollowUpTask')
            ? $measurement->thresholdFollowUpTask
            : null;

        if ($followUp instanceof Task) {
            $steps[] = self::taskStep(
                key: 'follow_up_task',
                label: __('esg.chain.follow_up_task'),
                task: $followUp,
                taskId: (int) $followUp->id,
            );
        }

        return $steps;
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     detail: ?string,
     *     url: ?string,
     *     status_label: ?string,
     *     status_modifier: ?string,
     * }>
     */
    public static function stepsForTask(Task $task): array
    {
        if ($task->esg_threshold_measurement_id !== null) {
            $measurement = $task->relationLoaded('esgThresholdMeasurement')
                ? $task->esgThresholdMeasurement
                : null;

            if ($measurement instanceof EsgMeasurement) {
                $measurement->loadMissing([
                    'indicator.translations',
                    'task',
                    'thresholdFollowUpTask',
                ]);

                return self::stepsForMeasurement($measurement);
            }
        }

        $measurement = EsgMeasurement::query()
            ->where('task_id', $task->id)
            ->with(['indicator.translations', 'task', 'thresholdFollowUpTask'])
            ->first();

        if ($measurement instanceof EsgMeasurement) {
            return self::stepsForMeasurement($measurement);
        }

        if ($task->issue?->esg_indicator_id !== null) {
            return [[
                'key' => 'esg_task',
                'label' => __('esg.chain.esg_task'),
                'detail' => $task->issue->esgIndicator?->localizedName(),
                'url' => route('esg.measurements.index', array_filter([
                    'indicator' => $task->issue->esg_indicator_id,
                    'unit' => $task->issue->unit_id,
                ])),
                'status_label' => __($task->status->labelKey()),
                'status_modifier' => $task->status->pillModifier(),
            ]];
        }

        return [];
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     detail: ?string,
     *     url: ?string,
     *     status_label: ?string,
     *     status_modifier: ?string,
     * }
     */
    private static function taskStep(string $key, string $label, ?Task $task, int $taskId): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'detail' => null,
            'url' => route('tasks.show', $taskId),
            'status_label' => $task !== null ? __($task->status->labelKey()) : null,
            'status_modifier' => $task?->status->pillModifier(),
        ];
    }

    private static function pointHistoryUrl(EsgMeasurement $measurement): ?string
    {
        if ($measurement->unit_id === null) {
            return null;
        }

        return route('esg.point.history', array_filter([
            'unit' => $measurement->unit_id,
            'indicator' => $measurement->esg_indicator_id,
        ]));
    }
}
