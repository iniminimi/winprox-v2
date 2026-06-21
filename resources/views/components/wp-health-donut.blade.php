@props([
    'percentComplete' => 100,
    'incompleteFraction' => 0.0,
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
    $completeLength = $circumference * (max(0, min(100, $percentComplete)) / 100);
    $incompleteLength = max(0, $circumference - $completeLength);
    $dimension = ($radius * 2) + $stroke;
    $center = $radius + ($stroke / 2);
@endphp

<div @class(['wp-health-donut', 'wp-health-donut--'.$size]) aria-hidden="true">
    <svg
        class="wp-health-donut__svg"
        width="{{ $dimension }}"
        height="{{ $dimension }}"
        viewBox="0 0 {{ $dimension }} {{ $dimension }}"
        role="img"
        aria-label="{{ __('health.donut_aria', ['percent' => $percentComplete]) }}"
    >
        <circle
            class="wp-health-donut__track"
            cx="{{ $center }}"
            cy="{{ $center }}"
            r="{{ $normalizedRadius }}"
            fill="none"
            stroke-width="{{ $stroke }}"
        />
        @if ($completeLength > 0)
            <circle
                class="wp-health-donut__segment wp-health-donut__segment--complete"
                cx="{{ $center }}"
                cy="{{ $center }}"
                r="{{ $normalizedRadius }}"
                fill="none"
                stroke-width="{{ $stroke }}"
                stroke-dasharray="{{ $completeLength }} {{ $circumference }}"
                stroke-dashoffset="0"
                transform="rotate(-90 {{ $center }} {{ $center }})"
            />
        @endif
        @if ($incompleteLength > 0 && $incompleteFraction > 0)
            <circle
                class="wp-health-donut__segment wp-health-donut__segment--incomplete"
                cx="{{ $center }}"
                cy="{{ $center }}"
                r="{{ $normalizedRadius }}"
                fill="none"
                stroke-width="{{ $stroke }}"
                stroke-dasharray="{{ $incompleteLength }} {{ $circumference }}"
                stroke-dashoffset="{{ -$completeLength }}"
                transform="rotate(-90 {{ $center }} {{ $center }})"
            />
        @endif
    </svg>
    <span class="wp-health-donut__label wp-tabular">{{ $percentComplete }}%</span>
</div>
