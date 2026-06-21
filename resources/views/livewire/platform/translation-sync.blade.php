<div class="wp-stack" @if ($isLocalOperator) wire:poll.3s @endif>
    <x-wp-page-head-title
        icon="issues"
        :title="__('platform.translation_sync.title')"
        :subtitle="$isLocalOperator ? __('platform.translation_sync.subtitle') : __('platform.translation_sync.server_subtitle')"
        help-page="platform.translations"
    />

    @if ($isLocalOperator)
        @if ($flashMessage)
            <div class="wp-card wp-card-pad" role="status">
                <p class="wp-text-body {{ $flashType === 'error' ? 'wp-text-danger' : '' }}">{{ $flashMessage }}</p>
            </div>
        @endif

        <div class="wp-card wp-card-pad wp-stack">
            <p class="wp-muted">{{ __('platform.translation_sync.intro') }}</p>

            <div class="wp-stack-tight">
                <h2 class="wp-section-title">{{ __('platform.translation_sync.workflow_title') }}</h2>
                <ol class="wp-muted" style="margin: 0; padding-left: 1.25rem;">
                    <li style="margin-bottom: 0.75rem;">
                        {{ __('platform.translation_sync.workflow_step_1') }}
                        <pre class="wp-muted" style="margin: 0.5rem 0 0; padding: 0.75rem; background: var(--wp-surface-2); border-radius: var(--wp-radius); overflow-x: auto;"><code>cd {{ base_path() }}
php artisan queue:work</code></pre>
                        <span style="display: block; margin-top: 0.35rem;">{{ __('platform.translation_sync.workflow_step_1_note') }}</span>
                    </li>
                    <li>{{ __('platform.translation_sync.workflow_step_2') }}</li>
                </ol>
            </div>

            @if ($useSyncQueue)
                <p class="wp-text-body wp-text-danger">{{ __('platform.translation_sync.sync_queue_warning') }}</p>
            @endif
            <p class="wp-muted">{{ __('platform.translation_sync.configured_as', [
                'host' => config('translation_sync.ssh_host'),
                'path' => config('translation_sync.remote_path'),
            ]) }}</p>
            <button
                    type="button"
                    class="btn btn--primary"
                    wire:click="start"
                    wire:loading.attr="disabled"
                    @if ($status && in_array($status['phase'] ?? '', ['queued', 'exporting_remote', 'downloading', 'translating', 'uploading', 'importing_remote'], true) && ! ($isStuck ?? false)) disabled @endif
                >
                    <x-wp-spinner wire:loading wire:target="start" class="wp-mr-2" />
                    {{ __('platform.translation_sync.start') }}
            </button>
        </div>

        @if ($status)
            @php
                $phase = (string) ($status['phase'] ?? '');
                $activePhases = ['queued', 'exporting_remote', 'downloading', 'translating', 'uploading', 'importing_remote'];
                $isRunning = in_array($phase, $activePhases, true);
                $total = (int) ($status['total'] ?? 0);
                $completed = (int) ($status['completed'] ?? 0);
                $percent = $total > 0 ? min(100, (int) round(($completed / $total) * 100)) : 0;
            @endphp

            <div class="wp-card wp-card-pad wp-stack-tight" role="status" aria-live="polite">
                <div class="wp-cluster">
                    @if ($isRunning)
                        <x-wp-spinner size="lg" :visible="true" />
                    @endif
                    <h2 class="wp-section-title">{{ __('platform.translation_sync.status_title') }}</h2>
                </div>

                <p class="wp-text-body">
                    {{ __('platform.translation_sync.phase_' . ($phase ?: 'unknown')) }}
                </p>

                @if ($isRunning)
                    @if ($phase === 'translating' && $total > 0)
                        <div
                            class="wp-progress"
                            role="progressbar"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="{{ $percent }}"
                            aria-label="{{ __('platform.translation_sync.progress', ['completed' => $completed, 'total' => $total]) }}"
                        >
                            <div class="wp-progress__bar" style="width: {{ $percent }}%;"></div>
                        </div>
                        <p class="wp-muted">
                            {{ __('platform.translation_sync.progress_percent', [
                                'completed' => $completed,
                                'total' => $total,
                                'percent' => $percent,
                            ]) }}
                        </p>
                        @if (! empty($status['current_issue_id']))
                            <p class="wp-muted">
                                {{ __('platform.translation_sync.current_item_issue', [
                                    'id' => $status['current_issue_id'],
                                    'locale' => strtoupper((string) ($status['current_locale'] ?? '')),
                                ]) }}
                            </p>
                        @elseif (! empty($status['current_announcement_id']))
                            <p class="wp-muted">
                                {{ __('platform.translation_sync.current_item_announcement', [
                                    'id' => $status['current_announcement_id'],
                                    'locale' => strtoupper((string) ($status['current_locale'] ?? '')),
                                ]) }}
                            </p>
                        @endif
                    @else
                        <div class="wp-progress wp-progress--indeterminate" aria-hidden="true">
                            <div class="wp-progress__bar"></div>
                        </div>
                    @endif
                @endif

                @if (! empty($status['updated_at']))
                    <p class="wp-muted">{{ __('platform.translation_sync.status_at', ['datetime' => $status['updated_at']]) }}</p>
                @endif

                @if ($isStuck ?? false)
                    <p class="wp-text-body wp-text-danger">{{ __('platform.translation_sync.stalled') }}</p>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="resetStuck">
                        {{ __('platform.translation_sync.reset_stuck') }}
                    </button>
                @endif

                @if ($phase === 'completed')
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

                @if ($phase === 'failed' && ! empty($status['message']))
                    <p class="wp-muted">{{ $status['message'] }}</p>
                @endif
            </div>
        @endif
    @else
        <div class="wp-card wp-card-pad wp-stack-tight">
            <p class="wp-text-body">{{ __('platform.translation_sync.server_only_message') }}</p>
            @if ($pendingCount > 0)
                <p class="wp-muted">{{ __('platform.translation_sync.reminder_pending', ['count' => $pendingCount]) }}</p>
            @else
                <p class="wp-muted">{{ __('platform.translation_sync.reminder_none') }}</p>
            @endif
        </div>
    @endif
</div>
