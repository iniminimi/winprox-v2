@props([
    'title',
    'titleId' => null,
    'openByDefault' => false,
])

<div {{ $attributes->merge(['class' => 'wp-card wp-card-pad wp-stack-tight wp-settings-section']) }} x-data="{ open: @js((bool) $openByDefault) }">
    <button
        type="button"
        class="wp-settings-section-toggle"
        @click="open = !open"
        :aria-expanded="open"
    >
        <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
        <h2 @if ($titleId) id="{{ $titleId }}" @endif class="wp-section-title">{{ $title }}</h2>
    </button>
    <div class="wp-disclosure-panel wp-stack-tight" x-show="open" x-cloak>
        {{ $slot }}
    </div>
</div>
