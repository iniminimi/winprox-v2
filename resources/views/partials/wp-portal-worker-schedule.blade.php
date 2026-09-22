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
            <thead>
                <tr>
                    <th scope="col">{{ __('time.portal.schedule.columns.date') }}</th>
                    <th scope="col">{{ __('time.portal.schedule.columns.duty') }}</th>
                    <th scope="col">{{ __('time.portal.schedule.columns.hours') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($schedule->entries as $entry)
                    <tr
                        wire:key="schedule-entry-{{ $entry->date }}-{{ $entry->kind }}"
                        @class([
                            'wp-portal-schedule__row--week-start' => $entry->weekStart,
                            'wp-portal-schedule__row--today' => $entry->isToday,
                        ])
                    >
                        <td class="wp-portal-schedule__day">{{ $entry->dayLabel }}</td>
                        <td class="wp-portal-schedule__duty">{{ $entry->duty !== '' ? $entry->duty : '—' }}</td>
                        <td class="wp-portal-schedule__hours">{{ $entry->hours !== '' ? $entry->hours : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
