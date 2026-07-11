@php
    $organisationLogoUrl = $tenant->logoPublicUrl();
    $organisationAddress = $tenant->organisationAddressLine();
@endphp

<x-layouts.print :title="__('time.print.document_title')">
    <div class="wp-container wp-stack">
        <div class="wp-page-head">
            <div class="wp-grow wp-stack-tight">
                <x-wp-page-head-title
                    icon="clock"
                    :title="__('time.print.title')"
                />
                <p class="wp-muted">{{ __('time.print.period', ['from' => $from->format('d-m-Y'), 'to' => $to->format('d-m-Y')]) }}</p>
                <div class="wp-cluster wp-no-print">
                    <button type="button" class="btn btn--primary btn--sm" onclick="window.print()">{{ __('time.print.button') }}</button>
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

        @if ($shifts->isEmpty())
            <div class="wp-card wp-card-pad">
                <p class="wp-muted">{{ __('time.print.empty') }}</p>
            </div>
        @else
            <div class="wp-card wp-card-pad">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('time.export.columns.date') }}</th>
                            <th>{{ __('time.export.columns.worker') }}</th>
                            <th>{{ __('time.export.columns.team') }}</th>
                            <th>{{ __('time.export.columns.clock_in') }}</th>
                            <th>{{ __('time.export.columns.clock_out') }}</th>
                            <th>{{ __('time.export.columns.break_minutes') }}</th>
                            <th>{{ __('time.export.columns.worked') }}</th>
                            <th>{{ __('time.export.columns.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($shifts as $shift)
                            <tr>
                                <td>{{ $shift->clock_in_at->format('d-m-Y') }}</td>
                                <td>{{ $shift->worker?->displayName() }}</td>
                                <td>{{ $shift->team?->name }}</td>
                                <td>{{ $shift->clock_in_at->format('H:i') }}</td>
                                <td>{{ $shift->clock_out_at?->format('H:i') ?? '—' }}</td>
                                <td>{{ \App\Support\Time\WorkDurationFormatter::format($shift->total_break_minutes) }}</td>
                                <td>{{ \App\Support\Time\WorkDurationFormatter::format($shift->netWorkMinutes()) }}</td>
                                <td>
                                    @if ($shift->status === \App\Enums\WorkShiftStatus::ForceClosed && $shift->clock_out_source === \App\Enums\ClockSource::Auto)
                                        {{ __('time.status.auto_closed') }}
                                    @else
                                        {{ __('time.status.'.$shift->status->value) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="wp-muted wp-text-sm">{{ __('time.print.total_worked', ['duration' => \App\Support\Time\WorkDurationFormatter::format($totalNetMinutes)]) }}</p>
            </div>
        @endif
    </div>
</x-layouts.print>
