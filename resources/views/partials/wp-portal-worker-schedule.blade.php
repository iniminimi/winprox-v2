<nav class="wp-pagination" aria-label="{{ __('time.portal.schedule.month_nav') }}">
    <div class="wp-pagination__pages">
        <button type="button" class="wp-pagination__control" wire:click="previousScheduleMonth" aria-label="{{ __('time.portal.schedule.previous_month') }}">‹</button>
        <span class="wp-pagination__page is-active">{{ $scheduleMonthLabel }}</span>
        <button type="button" class="wp-pagination__control" wire:click="nextScheduleMonth" aria-label="{{ __('time.portal.schedule.next_month') }}">›</button>
    </div>
</nav>

<div class="wp-card wp-card-pad wp-portal-schedule">
    @if ($schedule->entries === [])
        <p class="wp-muted">{{ __('time.portal.schedule.empty') }}</p>
    @else
        <div class="wp-portal-schedule__lines">
            @foreach ($schedule->entries as $entry)
                <p class="wp-portal-schedule__line" wire:key="schedule-entry-{{ $entry->date }}-{{ $entry->kind }}">{{ $entry->line }}</p>
            @endforeach
        </div>
    @endif
</div>
