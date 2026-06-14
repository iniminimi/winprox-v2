@props([
    'icon' => null,
    'title',
    'helpPage' => null,
    'subtitle' => null,
    'variant' => 'app',
])

<div @class([
    'wp-cluster wp-page-head-main',
    'wp-portal-section-head' => $variant === 'portal',
])>
    @if ($icon)
        <span @class($variant === 'portal' ? 'wp-icon-frame' : 'wp-page-icon') aria-hidden="true">
            <x-wp-icon :name="$icon" />
        </span>
    @endif
    <div class="wp-stack-tight wp-grow">
        @if (isset($toolbar))
            <div class="wp-cluster">
                <div class="wp-page-title-row">
                    <h1 class="wp-page-title">{{ $title }}</h1>
                    @if ($helpPage)
                        <x-wp-page-help :page="$helpPage" />
                    @endif
                </div>
                {{ $toolbar }}
            </div>
        @else
            <div class="wp-page-title-row">
                <h1 class="wp-page-title">{{ $title }}</h1>
                @if ($helpPage)
                    <x-wp-page-help :page="$helpPage" />
                @endif
            </div>
        @endif
        @if ($subtitle)
            <p class="wp-muted">{{ $subtitle }}</p>
        @endif
        {{ $slot }}
    </div>
</div>
