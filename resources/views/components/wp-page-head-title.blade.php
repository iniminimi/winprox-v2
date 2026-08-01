@props([
    'icon' => null,
    'assistantVideo' => null,
    'title',
    'helpPage' => null,
    'subtitle' => null,
    'variant' => 'app',
])

<div @class([
    'wp-cluster wp-page-head-main',
    'wp-card wp-card-pad' => $variant === 'portal',
])>
    @if ($assistantVideo)
        <span class="wp-page-icon wp-page-icon--assistant" aria-hidden="true">
            <video
                class="wp-page-icon__video"
                src="{{ $assistantVideo }}"
                width="80"
                height="80"
                autoplay
                muted
                playsinline
                preload="auto"
            ></video>
        </span>
    @elseif ($icon)
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
