<x-wp-report-print
    :title="__('reports.unit_measurements.title')"
    :document-title="__('reports.unit_measurements.document_title')"
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
        <div class="wp-list wp-stack">
            @foreach ($measurements as $measurement)
                <div class="wp-card wp-card-pad wp-cluster wp-cluster--spread wp-report-print__card">
                    <div class="wp-stack-tight">
                        <div class="wp-cluster wp-cluster--wrap">
                            <strong>{{ $measurement->recorded_at?->format('d-m-Y H:i') }}</strong>
                            <span class="wp-pill wp-pill--progress">{{ $measurement->field?->name }}</span>
                        </div>
                        <p class="wp-text-body">
                            {{ $measurement->displayValue() }}
                            · {{ $measurement->location?->name }}
                            · {{ $measurement->unit?->name }}
                        </p>
                        <p class="wp-muted wp-text-sm">
                            {{ $measurement->worker?->displayName() ?? __('unit_measurements.history.reporter_unknown') }}
                            · {{ __('unit_measurements.sources.'.$measurement->source->value) }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-wp-report-print>
