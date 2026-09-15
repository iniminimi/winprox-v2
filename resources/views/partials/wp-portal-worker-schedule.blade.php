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
        <table class="wp-portal-schedule__table">
            <tbody>
                @foreach ($schedule->entries as $entry)
                    <tr
                        wire:key="schedule-entry-{{ $entry->date }}-{{ $entry->kind }}"
                        @class(['wp-portal-schedule__row--week-start' => $entry->weekStart])
                    >
                        <td class="wp-portal-schedule__day">{{ $entry->dayLabel }}</td>
                        <td class="wp-portal-schedule__sep" aria-hidden="true">:</td>
                        <td class="wp-portal-schedule__detail">{{ $entry->detail }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
