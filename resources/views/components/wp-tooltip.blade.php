@props([
    'text',
    'wrap' => false,
])

{{--
  WinProx hover-ballon (zelfde als unit-GPS).
  Gebruik: <x-wp-tooltip :text="__('…')">…trigger…</x-wp-tooltip>
--}}
<span {{ $attributes->class('wp-tooltip') }}>
    {{ $slot }}
    <span
        @class([
            'wp-tooltip__bubble',
            'wp-tooltip__bubble--wrap' => $wrap,
        ])
        role="tooltip"
    >{{ $text }}</span>
</span>
