@props([
    'points' => [],
    'unit' => null,
])

@php
    $pointCount = count($points);
    $values = collect($points)->pluck('value')->map(fn ($value) => (float) $value);
    $maxValue = max(1.0, (float) $values->max());
    $minValue = (float) $values->min();
    $range = max($maxValue - $minValue, 1.0);

    $width = 640;
    $height = 220;
    $paddingX = 28;
    $paddingY = 24;
    $plotWidth = $width - ($paddingX * 2);
    $plotHeight = $height - ($paddingY * 2);

    $polylinePoints = [];
    foreach ($points as $index => $point) {
        $x = $paddingX + ($pointCount === 1 ? ($plotWidth / 2) : ($index / ($pointCount - 1)) * $plotWidth);
        $normalized = (((float) $point['value']) - $minValue) / $range;
        $y = $paddingY + $plotHeight - ($normalized * $plotHeight);
        $polylinePoints[] = round($x, 1).','.round($y, 1);
    }

    $polyline = implode(' ', $polylinePoints);
    $firstLabel = $points[0]['label'] ?? '';
    $lastLabel = $points[$pointCount - 1]['label'] ?? '';
    $midLabel = $pointCount > 2 ? ($points[(int) floor(($pointCount - 1) / 2)]['label'] ?? '') : '';
@endphp

<div class="wp-esg-trend-chart">
    @if ($pointCount === 0)
        <p class="wp-muted">{{ __('esg.dashboard.trend.empty') }}</p>
    @else
        <svg
            class="wp-esg-trend-chart__svg"
            viewBox="0 0 {{ $width }} {{ $height }}"
            role="img"
            aria-label="{{ __('esg.dashboard.trend.aria', ['count' => $pointCount]) }}"
            preserveAspectRatio="none"
        >
            <line
                class="wp-esg-trend-chart__axis"
                x1="{{ $paddingX }}"
                y1="{{ $height - $paddingY }}"
                x2="{{ $width - $paddingX }}"
                y2="{{ $height - $paddingY }}"
            />
            <polyline
                class="wp-esg-trend-chart__line"
                points="{{ $polyline }}"
                fill="none"
            />
            @foreach ($points as $index => $point)
                @php
                    $x = $paddingX + ($pointCount === 1 ? ($plotWidth / 2) : ($index / ($pointCount - 1)) * $plotWidth);
                    $normalized = (((float) $point['value']) - $minValue) / $range;
                    $y = $paddingY + $plotHeight - ($normalized * $plotHeight);
                @endphp
                <circle
                    class="wp-esg-trend-chart__dot"
                    cx="{{ round($x, 1) }}"
                    cy="{{ round($y, 1) }}"
                    r="4"
                >
                    <title>{{ $point['label'] }}: {{ number_format((float) $point['value'], 2, ',', '.') }}{{ filled($unit) ? ' '.$unit : '' }}</title>
                </circle>
            @endforeach
        </svg>
        <div class="wp-esg-trend-chart__labels">
            <span>{{ $firstLabel }}</span>
            @if ($midLabel !== '' && $midLabel !== $firstLabel && $midLabel !== $lastLabel)
                <span>{{ $midLabel }}</span>
            @endif
            @if ($lastLabel !== '' && $lastLabel !== $firstLabel)
                <span>{{ $lastLabel }}</span>
            @endif
        </div>
    @endif
</div>
