<x-wp-report-print
    :title="__('sense.readings.title', ['place' => $place])"
    :document-title="__('sense.readings.document_title', ['place' => $place])"
    :tenant="$tenant"
    :truncated="$truncated"
    :limit="$limit"
    :row-count="$readings->count()"
>
    <div class="wp-card wp-card-pad wp-stack-tight">
        @if ($readings->isEmpty())
            <p class="wp-muted">{{ __('reports.empty') }}</p>
        @else
            @foreach ($readings as $reading)
                <p class="wp-text-body">{{ \App\Support\Sense\SenseReadingLine::text($reading) }}</p>
            @endforeach
        @endif
    </div>
</x-wp-report-print>
