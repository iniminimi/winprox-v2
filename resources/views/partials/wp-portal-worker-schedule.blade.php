<nav class="wp-pagination" aria-label="{{ __('time.portal.schedule.month_nav') }}">
    <div class="wp-pagination__pages">
        <button type="button" class="wp-pagination__control" wire:click="previousScheduleMonth" aria-label="{{ __('time.portal.schedule.previous_month') }}">‹</button>
        <span class="wp-pagination__page is-active">{{ $scheduleMonthLabel }}</span>
        <button type="button" class="wp-pagination__control" wire:click="nextScheduleMonth" aria-label="{{ __('time.portal.schedule.next_month') }}">›</button>
    </div>
</nav>

@if (($schedulePublishedLabel ?? null) !== null || ($scheduleChangedDaysLabel ?? '') !== '')
    <div class="wp-flash wp-flash--muted wp-portal-schedule__notice">
        @if (($schedulePublishedLabel ?? null) !== null)
            <p>{{ __('time.portal.schedule.published_notice', ['date' => $schedulePublishedLabel]) }}</p>
        @endif
        @if (($scheduleChangedDaysLabel ?? '') !== '')
            <p>{{ __('time.portal.schedule.changed_notice', ['days' => $scheduleChangedDaysLabel]) }}</p>
        @endif
    </div>
@endif

@if ($schedule->entries === [])
    <div class="wp-card wp-card-pad wp-portal-schedule">
        <p class="wp-muted">{{ __('time.portal.schedule.empty') }}</p>
    </div>
@else
    @foreach ($schedule->weeks() as $week)
        <div class="wp-card wp-card-pad wp-portal-schedule" wire:key="schedule-week-{{ $week[0]->date }}">
            <table class="wp-portal-schedule__table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('time.portal.schedule.columns.date') }}</th>
                        <th scope="col">{{ __('time.portal.schedule.columns.duty') }}</th>
                        <th scope="col">{{ __('time.portal.schedule.columns.hours') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($week as $entry)
                        <tr
                            wire:key="schedule-entry-{{ $entry->date }}-{{ $entry->kind }}-{{ $loop->index }}"
                            @class([
                                'wp-portal-schedule__row--today' => $entry->isToday,
                                'wp-portal-schedule__row--changed' => in_array($entry->date, $scheduleChangedDates ?? [], true),
                            ])
                        >
                            <td class="wp-portal-schedule__day">{{ $entry->dayLabel }}</td>
                            <td class="wp-portal-schedule__duty">
                                {{ $entry->duty !== '' ? $entry->duty : '—' }}
                                @if ($entry->description !== null)
                                    <span class="wp-muted wp-portal-schedule__note">{{ $entry->description }}</span>
                                @endif
                            </td>
                            <td class="wp-portal-schedule__hours">{{ $entry->hours !== '' ? $entry->hours : '—' }} @include('partials.wp-portal-clock-alert', ['alert' => $entry->clockAlert])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endif
