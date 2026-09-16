@php
    $organisationLogoUrl = $tenant->logoPublicUrl();
    $organisationAddress = $tenant->organisationAddressLine();
    $rows = $snapshot->rows !== []
        ? $snapshot->rows
        : array_map(fn ($worker) => ['type' => 'worker', 'worker_id' => $worker['id']], $snapshot->workers);
    $workersById = collect($snapshot->workers)->keyBy('id');
    $workLegend = collect($legendTypes)->filter(fn ($type) => $type->kind->isWork())->values();
    $absenceLegend = collect($legendTypes)->filter(fn ($type) => $type->kind->isAbsence())->values();
    $unitLegend = ($snapshot->groupMode ?? false)
        ? collect($snapshot->units)->unique('id')->values()
        : collect();
    $hasLegend = $workLegend->isNotEmpty() || $absenceLegend->isNotEmpty() || $unitLegend->isNotEmpty();
@endphp

<x-layouts.print :title="__('time.schedule.document_title')">
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 0.45cm;
            }

            .wp-roster-print,
            .wp-roster-print th,
            .wp-roster-print td,
            .wp-roster-print .wp-roster-color-preview {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                color-adjust: exact;
            }
        }
    </style>

    <div class="wp-container wp-stack wp-roster-print">
        <div class="wp-page-head wp-roster-print__head">
            <div class="wp-roster-print__head-start wp-stack-tight">
                <x-wp-page-head-title
                    icon="calendar"
                    :title="__('time.schedule.title')"
                />
                <p class="wp-muted">{{ $periodLabel }}</p>
                <div class="wp-cluster wp-no-print">
                    <button type="button" class="btn btn--primary btn--sm" onclick="window.print()">{{ __('common.button.print') }}</button>
                </div>
            </div>

            @if ($hasLegend)
                <div class="wp-roster-print__legend" aria-label="{{ __('time.schedule.legend_title') }}">
                    @foreach ($workLegend as $type)
                        <span class="wp-roster-print__legend-item">
                            <span class="wp-roster-color-preview {{ $type->color->hasFill() ? 'wp-roster-cell--'.$type->color->value : '' }}" aria-hidden="true"></span>
                            <strong>{{ $type->code }}</strong>
                            <span class="wp-muted">{{ $type->start_time }}–{{ $type->end_time }}</span>
                        </span>
                    @endforeach
                    @foreach ($absenceLegend as $type)
                        <span class="wp-roster-print__legend-item">
                            <span class="wp-roster-color-preview {{ $type->color->hasFill() ? 'wp-roster-cell--'.$type->color->value : '' }}" aria-hidden="true"></span>
                            <strong>{{ $type->code }}</strong>
                            <span class="wp-muted">{{ __('time.schedule.types.kinds.'.$type->kind->value) }}</span>
                        </span>
                    @endforeach
                    @foreach ($unitLegend as $unit)
                        <span class="wp-roster-print__legend-item wp-roster-print__legend-item--unit">
                            <strong>{{ $unit['code'] }}</strong>
                            <span class="wp-muted">{{ $unit['name'] }}</span>
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="wp-cluster wp-cluster--tight wp-page-actions wp-roster-print__head-end">
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
                    <colgroup>
                        <col class="wp-roster-print__name-col">
                        @foreach ($snapshot->dates as $date)
                            <col>
                        @endforeach
                    </colgroup>
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
                                            $rawDisplay = is_array($cell) ? trim((string) ($cell['display'] ?? '')) : '';
                                            $slash = strpos($rawDisplay, '/');
                                            $display = ($slash !== false && ! str_contains($rawDisplay, "\n") && substr_count($rawDisplay, '/') === 1)
                                                ? substr($rawDisplay, 0, $slash)."\n".strtoupper(substr($rawDisplay, $slash + 1))
                                                : $rawDisplay;
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
