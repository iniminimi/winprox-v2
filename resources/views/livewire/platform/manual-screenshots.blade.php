<div class="wp-stack">
    <x-wp-page-head-title
        icon="document"
        :title="__('platform.manual_screenshots.title')"
        :subtitle="__('platform.manual_screenshots.subtitle')"
    />

    @if ($flashMessage)
        <div class="wp-card wp-card-pad" role="status">
            <p class="wp-text-body {{ $flashType === 'error' ? 'wp-text-danger' : '' }}">{{ $flashMessage }}</p>
        </div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <p class="wp-muted">{{ __('platform.manual_screenshots.intro') }}</p>

        @if (! $isConfigured)
            <p class="wp-text-body">{{ __('platform.manual_screenshots.not_configured') }}</p>
        @else
            <p class="wp-muted">{{ __('platform.manual_screenshots.configured_as', ['email' => config('manual_capture.email')]) }}</p>
            <button
                type="button"
                class="btn btn--primary"
                wire:click="startCapture"
                wire:loading.attr="disabled"
            >
                <x-wp-spinner wire:loading wire:target="startCapture" class="wp-mr-2" />
                {{ __('platform.manual_screenshots.update') }}
            </button>
        @endif
    </div>

    @if ($status)
        <div class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('platform.manual_screenshots.status_title') }}</h2>
            <p class="wp-text-body">
                {{ __('platform.manual_screenshots.status_' . ($status['status'] ?? 'unknown')) }}
            </p>
            @if (! empty($status['updated_at']))
                <p class="wp-muted">{{ __('platform.manual_screenshots.status_at', ['datetime' => $status['updated_at']]) }}</p>
            @endif
            @if (! empty($status['message']) && ($status['status'] ?? '') === 'failed')
                <p class="wp-muted">{{ $status['message'] }}</p>
            @endif
            @if (! empty($status['output']) && ($status['status'] ?? '') === 'completed')
                <pre class="wp-muted">{{ $status['output'] }}</pre>
            @endif
        </div>
    @endif
</div>
