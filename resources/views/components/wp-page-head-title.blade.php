@props([
    'icon' => null,
    'assistantVideo' => null,
    'assistantVideoLoop' => false,
    'title',
    'helpPage' => null,
    'subtitle' => null,
    'variant' => 'app',
])

<div @class([
    'wp-cluster wp-page-head-main',
    'wp-page-head-main--assistant' => (bool) $assistantVideo,
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
                @if ($assistantVideoLoop) loop @endif
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
        <div class="wp-page-title-bar">
            <div class="wp-page-title-row">
                <h1 class="wp-page-title">{{ $title }}</h1>
                @if ($helpPage)
                    <x-wp-page-help :page="$helpPage" />
                @endif
                {{ $titleActions ?? '' }}
                @if (isset($toolbar))
                    {{ $toolbar }}
                @endif
            </div>
            @if ($variant === 'app')
                <x-wp-flip-clock />
            @endif
        </div>
        @if ($subtitle)
            <p class="wp-muted">{{ $subtitle }}</p>
        @endif
        {{ $slot }}
    </div>
</div>
