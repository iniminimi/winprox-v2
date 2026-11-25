<div
    class="wp-flip-clock"
    x-data="wpFlipClock(@js(__('common.clock')))"
    x-cloak
    role="timer"
    :aria-label="label"
>
    @foreach ([0, 1] as $group)
        <span class="wp-flip-clock__group">
            @foreach ([0, 1] as $offset)
                @php $index = $group * 2 + $offset; @endphp
                <span class="wp-flip" :class="{ 'is-flipping': digits[{{ $index }}].flip }">
                    <span class="wp-flip__face wp-flip__face--top" aria-hidden="true"><span x-text="digits[{{ $index }}].value"></span></span>
                    <span class="wp-flip__face wp-flip__face--bottom" aria-hidden="true"><span x-text="digits[{{ $index }}].flip ? digits[{{ $index }}].from : digits[{{ $index }}].value"></span></span>
                    <span class="wp-flip__leaf" aria-hidden="true">
                        <span class="wp-flip__leaf-face wp-flip__leaf-face--front"><span x-text="digits[{{ $index }}].from"></span></span>
                        <span class="wp-flip__leaf-face wp-flip__leaf-face--back"><span x-text="digits[{{ $index }}].value"></span></span>
                    </span>
                </span>
            @endforeach
        </span>
        @if ($group === 0)
            <span class="wp-flip-clock__colon" aria-hidden="true"></span>
        @endif
    @endforeach
</div>
