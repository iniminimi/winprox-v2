@props([
    'title',
    'subtitle' => null,
    'count' => null,
])

<div {{ $attributes->merge(['class' => 'wp-card wp-card-pad wp-stack-tight wp-settings-section']) }} x-data="{ open: false }">
    <button
        type="button"
        class="wp-settings-section-toggle wp-settings-section-toggle--stacked"
        @click="open = !open"
        :aria-expanded="open"
    >
        <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
        <span class="wp-grow wp-stack-tight">
            <span class="wp-cluster">
                <h2 class="wp-section-title">{{ $title }}</h2>
                @if ($count !== null)
                    <span class="wp-pill wp-pill--closed">{{ $count }}</span>
                @endif
            </span>
            @if ($subtitle)
                <span class="wp-muted" x-show="!open">{{ $subtitle }}</span>
            @endif
        </span>
    </button>
    <div class="wp-disclosure-panel wp-stack" x-show="open" x-cloak>
        @isset($toolbar)
            <div class="wp-row">
                @if ($subtitle)
                    <p class="wp-muted wp-grow">{{ $subtitle }}</p>
                @endif
                <div class="wp-cluster">{{ $toolbar }}</div>
            </div>
        @endisset
        {{ $slot }}
    </div>
</div>
