<div class="wp-stack" @if ($isConfigured) wire:poll.3s @endif>
    <x-wp-page-head-title
        icon="issues"
        :title="__('platform.translation_sync.title')"
        :subtitle="__('platform.translation_sync.subtitle')"
    />

    @if ($flashMessage)
        <div class="wp-card wp-card-pad" role="status">
            <p class="wp-text-body {{ $flashType === 'error' ? 'wp-text-danger' : '' }}">{{ $flashMessage }}</p>
        </div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <p class="wp-muted">{{ __('platform.translation_sync.intro') }}</p>

        @if (! $isConfigured)
            <p class="wp-text-body">{{ __('platform.translation_sync.not_enabled') }}</p>
            <p class="wp-muted">{{ __('platform.translation_sync.setup_hint') }}</p>
        @else
            <p class="wp-muted">{{ __('platform.translation_sync.configured_as', [
                'host' => config('translation_sync.ssh_host'),
                'path' => config('translation_sync.remote_path'),
            ]) }}</p>
            <button
                type="button"
                class="btn btn--primary"
                wire:click="start"
                wire:loading.attr="disabled"
            >
                <x-wp-spinner wire:loading wire:target="start" class="wp-mr-2" />
                {{ __('platform.translation_sync.start') }}
            </button>
        @endif
    </div>

    @if ($status)
        <div class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('platform.translation_sync.status_title') }}</h2>
            <p class="wp-text-body">
                {{ __('platform.translation_sync.phase_' . ($status['phase'] ?? 'unknown')) }}
            </p>

            @if (($status['phase'] ?? '') === 'translating' && ! empty($status['total']))
                <p class="wp-muted">
                    {{ __('platform.translation_sync.progress', [
                        'completed' => (int) ($status['completed'] ?? 0),
                        'total' => (int) $status['total'],
                    ]) }}
                </p>
                @if (! empty($status['current_issue_id']))
                    <p class="wp-muted">
                        {{ __('platform.translation_sync.current_item', [
                            'issue' => $status['current_issue_id'],
                            'locale' => strtoupper((string) ($status['current_locale'] ?? '')),
                        ]) }}
                    </p>
                @endif
            @endif

            @if (! empty($status['updated_at']))
                <p class="wp-muted">{{ __('platform.translation_sync.status_at', ['datetime' => $status['updated_at']]) }}</p>
            @endif

            @if (($status['phase'] ?? '') === 'completed')
                <p class="wp-text-body">
                    {{ __('platform.translation_sync.completed_summary', [
                        'imported' => (int) ($status['imported'] ?? 0),
                        'total' => (int) ($status['total'] ?? 0),
                    ]) }}
                </p>
                @if (($status['message'] ?? '') === 'nothing_pending')
                    <p class="wp-muted">{{ __('platform.translation_sync.nothing_pending') }}</p>
                @endif
            @endif

            @if (($status['phase'] ?? '') === 'failed' && ! empty($status['message']))
                <p class="wp-muted">{{ $status['message'] }}</p>
            @endif
        </div>
    @endif
</div>
