<nav class="wp-pagination" aria-label="{{ __('time.portal.team_schedule.week_nav') }}">
    <div class="wp-pagination__pages">
        <button type="button" class="wp-pagination__control" wire:click="previousTeamScheduleWeek" aria-label="{{ __('time.portal.team_schedule.previous_week') }}">‹</button>
        <button type="button" class="wp-pagination__page is-active" wire:click="thisTeamScheduleWeek">{{ $teamScheduleWeekLabel }}</button>
        <button type="button" class="wp-pagination__control" wire:click="nextTeamScheduleWeek" aria-label="{{ __('time.portal.team_schedule.next_week') }}">›</button>
    </div>
</nav>

<div class="wp-card wp-card-pad wp-portal-schedule">
    @if ($teamSchedule->days === [])
        <p class="wp-muted">{{ __('time.portal.team_schedule.empty') }}</p>
    @else
        <table class="wp-portal-schedule__table">
            <thead>
                <tr>
                    <th scope="col">{{ __('time.portal.team_schedule.columns.name') }}</th>
                    <th scope="col">{{ __('time.portal.schedule.columns.duty') }}</th>
                    <th scope="col">{{ __('time.portal.schedule.columns.hours') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($teamSchedule->days as $day)
                    <tr class="wp-portal-schedule__day-group" wire:key="team-day-{{ $day->date }}">
                        <td colspan="3" @class(['wp-portal-schedule__day-group-label', 'wp-portal-schedule__day-group-label--today' => $day->isToday])>{{ $day->dayLabel }}</td>
                    </tr>
                    @foreach ($day->entries as $entry)
                        <tr
                            wire:key="team-entry-{{ $day->date }}-{{ $loop->index }}"
                            @class(['wp-portal-schedule__row--self' => $entry->isViewer])
                        >
                            <td class="wp-portal-schedule__day">
                                {{ $entry->workerName }}
                                @if ($entry->isViewer)
                                    <span class="wp-pill wp-pill--new">{{ __('time.portal.team_schedule.you') }}</span>
                                @endif
                            </td>
                            <td class="wp-portal-schedule__duty">{{ $entry->duty !== '' ? $entry->duty : '—' }}</td>
                            <td class="wp-portal-schedule__hours">{{ $entry->hours !== '' ? $entry->hours : '—' }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @endif
</div>
