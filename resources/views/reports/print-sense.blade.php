<x-wp-report-print
    :title="__('sense.readings.title', ['place' => $place])"
    :document-title="__('sense.readings.document_title', ['place' => $place])"
    :tenant="$tenant"
    :truncated="$truncated"
    :limit="$limit"
    :row-count="$readings->count()"
>
    @if ($readings->isEmpty())
        <div class="wp-card wp-card-pad">
            <p class="wp-muted">{{ __('reports.empty') }}</p>
        </div>
    @else
        <div class="wp-list wp-stack">
            @foreach ($readings as $reading)
                @php
                    $at = $reading->recorded_at?->timezone(config('app.timezone'));
                @endphp
                <div class="wp-card wp-card-pad wp-report-print__card">
                    <p class="wp-text-body"><strong>{{ $at?->format('d-m-Y H:i') }}</strong></p>
                    <p class="wp-muted">
                        {{ __('sense.metrics.temperature') }} {{ \App\Support\Sense\SenseNumber::withUnit(\App\Enums\SenseMetric::Temperature, (float) $reading->temperature) }}
                        · {{ __('sense.metrics.humidity') }} {{ \App\Support\Sense\SenseNumber::withUnit(\App\Enums\SenseMetric::Humidity, (float) $reading->humidity) }}
                        · {{ __('sense.metrics.co2') }} {{ \App\Support\Sense\SenseNumber::withUnit(\App\Enums\SenseMetric::Co2, (float) $reading->co2) }}
                        · {{ __('sense.metrics.voc') }} {{ \App\Support\Sense\SenseNumber::withUnit(\App\Enums\SenseMetric::Voc, (float) $reading->voc) }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif
</x-wp-report-print>
