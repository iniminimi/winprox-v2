@props([
    'segments' => [],
    'centerLabel' => '',
    'size' => 'md',
])

@php
    $radius = match ($size) {
        'lg' => 52,
        'sm' => 32,
        default => 40,
    };
    $stroke = match ($size) {
        'lg' => 12,
        'sm' => 8,
        default => 10,
    };
    $normalizedRadius = $radius - ($stroke / 2);
    $circumference = 2 * M_PI * $normalizedRadius;
    $dimension = ($radius * 2) + $stroke;
    $center = $radius + ($stroke / 2);
    $visibleSegments = collect($segments)->filter(fn (array $segment): bool => ($segment['percent'] ?? 0) > 0)->values();
    $dashOffset = 0.0;
@endphp

<div @class(['wp-segment-donut', 'wp-segment-donut--'.$size])>
    @if ($visibleSegments->isEmpty())
        <p class="wp-muted">{{ __('esg.dashboard.distribution.empty') }}</p>
    @else
        <div class="wp-segment-donut__body">
            <svg
                class="wp-segment-donut__svg"
                width="{{ $dimension }}"
                height="{{ $dimension }}"
                viewBox="0 0 {{ $dimension }} {{ $dimension }}"
                role="img"
                aria-label="{{ __('esg.dashboard.distribution.aria', ['count' => $visibleSegments->count()]) }}"
            >
                <circle
                    class="wp-segment-donut__track"
                    cx="{{ $center }}"
                    cy="{{ $center }}"
                    r="{{ $normalizedRadius }}"
                    fill="none"
                    stroke-width="{{ $stroke }}"
                />
                @foreach ($visibleSegments as $segment)
                    @php
                        $length = $circumference * (max(0, min(100, (float) $segment['percent'])) / 100);
                    @endphp
                    @if ($length > 0)
                        <circle
                            @class(['wp-segment-donut__segment', 'wp-segment-donut__segment--'.$segment['key']])
                            cx="{{ $center }}"
                            cy="{{ $center }}"
                            r="{{ $normalizedRadius }}"
                            fill="none"
                            stroke-width="{{ $stroke }}"
                            stroke-dasharray="{{ $length }} {{ $circumference }}"
                            stroke-dashoffset="{{ -$dashOffset }}"
                            transform="rotate(-90 {{ $center }} {{ $center }})"
                        />
                        @php($dashOffset += $length)
                    @endif
                @endforeach
            </svg>
            @if ($centerLabel !== '')
                <span class="wp-segment-donut__label wp-tabular">{{ $centerLabel }}</span>
            @endif
        </div>
        <ul class="wp-segment-donut__legend">
            @foreach ($segments as $segment)
                <li class="wp-segment-donut__legend-row" wire:key="esg-category-{{ $segment['key'] }}">
                    <span @class(['wp-segment-donut__swatch', 'wp-segment-donut__segment--'.$segment['key']]) aria-hidden="true"></span>
                    <span class="wp-grow">{{ $segment['label'] }}</span>
                    <span class="wp-muted wp-tabular">{{ number_format((int) round((float) $segment['percent']), 0, ',', '.') }}%</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
