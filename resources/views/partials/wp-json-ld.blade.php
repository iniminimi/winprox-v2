@props([
    'graphs' => [],
])

@foreach ($graphs as $graph)
    @if (is_array($graph) && $graph !== [])
        <script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
@endforeach
