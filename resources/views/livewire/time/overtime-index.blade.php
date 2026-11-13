<div class="wp-stack" data-manual-capture="time-overtime">
    <x-wp-page-head-title
        icon="time"
        :title="__('time.nav.overtime')"
        help-page="time.overtime"
        :subtitle="__('time.overtime.subtitle', ['year' => $year])"
    />

    @include('partials.wp-time-nav', ['alarmCount' => $alarmCount])

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
                        @foreach ($monthLabels as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                        <th>{{ __('time.overtime.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            @foreach ($monthLabels as $month => $label)
                                <td @class(['wp-overtime-table__negative' => $row['months'][$month] < 0])>
                                    {{ \App\Support\Time\WorkDurationFormatter::signed($row['months'][$month]) }}
                                </td>
                            @endforeach
                            <td @class(['wp-overtime-table__negative' => $row['total'] < 0])>
                                <strong>{{ \App\Support\Time\WorkDurationFormatter::signed($row['total']) }}</strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
