@props(['issue'])
@if ($issue && $issue->is_recurring)
    <div class="wp-stack-tight" role="note">
        <p class="wp-muted">{{ __('issues.show.edit_task_recurring_hint') }}</p>
        @if ($issue->recurrence_interval_value && $issue->recurrence_interval_unit)
            <p class="wp-muted">{{ __('tasks.show.recurring_interval', [
                'value' => $issue->recurrence_interval_value,
                'unit' => __('issues.create.unit_'.$issue->recurrence_interval_unit->value),
            ]) }}</p>
        @endif
    </div>
@endif
