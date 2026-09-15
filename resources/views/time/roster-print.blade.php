@php
    $organisationLogoUrl = $tenant->logoPublicUrl();
    $organisationAddress = $tenant->organisationAddressLine();
    $rows = $snapshot->rows !== []
        ? $snapshot->rows
        : array_map(fn ($worker) => ['type' => 'worker', 'worker_id' => $worker['id']], $snapshot->workers);
    $workersById = collect($snapshot->workers)->keyBy('id');
@endphp

<x-layouts.print :title="__('time.schedule.document_title')">
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 0.8cm;
            }
        }
    </style>

    <div class="wp-container wp-stack wp-roster-print">
        <div class="wp-page-head">
            <div class="wp-grow wp-stack-tight">
                <x-wp-page-head-title
                    icon="calendar"
                    :title="__('time.schedule.title')"
                />
                <p class="wp-muted">{{ $periodLabel }}</p>
                <div class="wp-cluster wp-no-print">
                    <button type="button" class="btn btn--primary btn--sm" onclick="window.print()">{{ __('common.button.print') }}</button>
                </div>
            </div>
            <div class="wp-cluster wp-cluster--tight wp-page-actions">
                <div class="wp-sidebar-header-logo">
                    <img
                        src="{{ $organisationLogoUrl ?? asset('images/Winprox_logo_100.png') }}"
                        alt="{{ $tenant->name }}"
                    >
                </div>
                <p class="wp-muted">
                    <strong class="wp-text-body">{{ $tenant->name }}</strong>
                    @if ($organisationAddress)
                        <br>{{ $organisationAddress }}
                    @endif
                </p>
            </div>
        </div>

        @if ($snapshot->workers === [])
            <div class="wp-card wp-card-pad">
                <p class="wp-muted">{{ __('time.schedule.empty_workers') }}</p>
            </div>
        @else
            <div class="wp-card wp-card-pad wp-roster-print__sheet">
                <table class="wp-roster-print__table">
                    <thead>
                        @if ($period === 'month' && $snapshot->monthLabel !== '')
                            <tr>
                                <th></th>
                                <th colspan="{{ count($snapshot->dates) }}">{{ $snapshot->monthLabel }}</th>
                            </tr>
                        @endif
                        <tr>
                            <th>{{ __('time.schedule.column_name') }}</th>
                            @foreach ($snapshot->dates as $index => $date)
                                <th>
                                    @if ($period === 'month')
                                        {{ $snapshot->dayNumbers[$index] ?? '' }}
                                        <br>{{ $snapshot->dayLabels[$index] ?? '' }}
                                    @else
                                        {{ $snapshot->dayLabels[$index] ?? $date }}
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @if (($row['type'] ?? '') === 'section')
                                <tr class="wp-roster-print__section">
                                    <td colspan="{{ count($snapshot->dates) + 1 }}">{{ $row['label'] ?? '' }}</td>
                                </tr>
                            @else
                                @php
                                    $worker = $workersById->get($row['worker_id'] ?? null);
                                @endphp
                                @continue(! $worker)
                                <tr>
                                    <th scope="row">{{ $worker['name'] }}</th>
                                    @foreach ($snapshot->dates as $date)
                                        @php
                                            $cell = $snapshot->cells[$worker['id'].':'.$date] ?? null;
                                            $display = is_array($cell) ? (string) ($cell['display'] ?? '') : '';
                                            $color = is_array($cell) ? (string) ($cell['color'] ?? 'none') : 'none';
                                        @endphp
                                        <td @class([
                                            'wp-roster-print__cell',
                                            'wp-roster-cell--'.$color => $color !== '' && $color !== 'none',
                                        ])>{{ $display }}</td>
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.print>
