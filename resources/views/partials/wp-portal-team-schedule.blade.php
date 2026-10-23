<nav class="wp-pagination" aria-label="{{ __('time.portal.team_schedule.week_nav') }}">
    <div class="wp-pagination__pages">
        <button type="button" class="wp-pagination__control" wire:click="previousTeamScheduleWeek" aria-label="{{ __('time.portal.team_schedule.previous_week') }}">‹</button>
        <button type="button" class="wp-pagination__page is-active" wire:click="thisTeamScheduleWeek">{{ $teamScheduleWeekLabel }}</button>
        <button type="button" class="wp-pagination__control" wire:click="nextTeamScheduleWeek" aria-label="{{ __('time.portal.team_schedule.next_week') }}">›</button>
    </div>
</nav>

@if ($teamSchedule->days === [])
    <div class="wp-card wp-card-pad wp-portal-schedule">
        <p class="wp-muted">{{ __('time.portal.team_schedule.empty') }}</p>
    </div>
@else
    @foreach ($teamSchedule->days as $day)
        <div class="wp-card wp-card-pad wp-portal-schedule wp-portal-schedule--team" wire:key="team-day-{{ $day->date }}">
            <p @class(['wp-portal-schedule__day-group-label', 'wp-portal-schedule__day-group-label--today' => $day->isToday])>{{ $day->dayLabel }}</p>
            <table class="wp-portal-schedule__table">
                <tbody>
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
                </tbody>
            </table>
        </div>
    @endforeach
@endif
