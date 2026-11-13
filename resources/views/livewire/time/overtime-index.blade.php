<div class="wp-stack" data-manual-capture="time-overtime">
    <x-wp-page-head-title
        icon="time"
        :title="__('time.nav.overtime')"
        help-page="time.overtime"
        :subtitle="__('time.overtime.subtitle', ['year' => $year])"
    />

    @include('partials.wp-time-nav', ['alarmCount' => $alarmCount])

    <div class="wp-card wp-filter-panel">
        <div class="wp-filter-form">
            <div class="wp-filter-form__row">
                <div class="wp-filter-cell">
                    <label class="wp-filter-inline-label" for="overtime-team">{{ __('time.filters.team') }}</label>
                    <select id="overtime-team" class="wp-select" wire:model="teamFilter">
                        <option value="">{{ __('time.filters.all_teams') }}</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->localizedName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wp-filter-cell">
                    <label class="wp-filter-inline-label" for="overtime-clock-point">{{ __('time.filters.clock_point') }}</label>
                    <select id="overtime-clock-point" class="wp-select" wire:model="clockPointFilter">
                        <option value="">{{ __('time.filters.all_clock_points') }}</option>
                        @foreach ($clockPoints as $clockPoint)
                            <option value="{{ $clockPoint->id }}">{{ $clockPoint->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="wp-filter-form__actions">
                <button type="button" class="btn btn--primary btn--sm" wire:click="applyFilters">{{ __('time.filters.apply') }}</button>
            </div>
        </div>
    </div>

    @if ($rows === [])
        <div class="wp-card wp-card-pad">
            <p class="wp-muted">{{ __('time.overtime.empty') }}</p>
        </div>
    @else
        <div class="wp-card wp-overtime-table-wrap">
            <table class="wp-overtime-table">
                <thead>
                    <tr>
                        <th>{{ __('time.overtime.worker') }}</th>
                        <th>{{ __('time.overtime.total') }}</th>
                        <th class="wp-overtime-table__gap" aria-hidden="true"></th>
                        @foreach ($monthLabels as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td @class(['wp-overtime-table__total', 'wp-overtime-table__negative' => $row['total'] < 0])>
                                {{ \App\Support\Time\WorkDurationFormatter::signed($row['total']) }}
                            </td>
                            <td class="wp-overtime-table__gap" aria-hidden="true"></td>
                            @foreach ($monthLabels as $month => $label)
                                <td @class(['wp-overtime-table__negative' => $row['months'][$month] < 0])>
                                    {{ \App\Support\Time\WorkDurationFormatter::signed($row['months'][$month]) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
