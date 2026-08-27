@php
    use App\Support\Esg\EsgMeasurementPresenter;
@endphp

<x-wp-report-print
    :title="__('reports.esg.title')"
    :document-title="__('reports.esg.document_title')"
    :tenant="$tenant"
    :truncated="$truncated"
    :limit="$limit"
    :row-count="$measurements->count()"
>
    @if ($measurements->isEmpty())
        <div class="wp-card wp-card-pad">
            <p class="wp-muted">{{ __('reports.empty') }}</p>
        </div>
    @else
        <div class="wp-card wp-card-pad wp-stack-tight">
            <ul class="wp-list-plain wp-stack-tight">
                @foreach ($measurements as $measurement)
                    @php($outsideThresholds = EsgMeasurementPresenter::isOutsideThresholds($measurement))
                    <li class="wp-list-row wp-report-print__card">
                        <div>
                            <strong>{{ $measurement->indicator?->localizedName() ?? '—' }}</strong>
                            <p class="wp-muted wp-text-sm">
                                {{ EsgMeasurementPresenter::displayValue($measurement) }}
                            </p>
                            @if ($measurement->corrects_measurement_id && $measurement->correctsMeasurement)
                                <p class="wp-muted wp-text-sm">
                                    {{ __('esg.measurements.corrects_original', [
                                        'value' => EsgMeasurementPresenter::displayValue($measurement->correctsMeasurement),
                                    ]) }}
                                </p>
                            @endif
                            <p class="wp-muted wp-text-sm">
                                {{ $measurement->location?->localizedName() ?? '—' }}
                                @if ($measurement->unit)
                                    · {{ $measurement->unit->localizedName() }}
                                @endif
                                · {{ optional($measurement->recorded_at)->format('d-m-Y H:i') }}
                            </p>
                            @if ($measurement->worker)
                                <p class="wp-muted wp-text-sm">
                                    {{ trim($measurement->worker->first_name.' '.$measurement->worker->last_name) }}
                                </p>
                            @endif
                        </div>
                        <div class="wp-cluster">
                            @if ($measurement->corrects_measurement_id)
                                <span class="wp-pill wp-pill--closed">{{ __('esg.measurements.correction') }}</span>
                            @endif
                            @if ($outsideThresholds)
                                <span class="wp-pill wp-pill--progress">{{ __('esg.measurements.outside_thresholds') }}</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-wp-report-print>
