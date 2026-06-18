@if ($visible)
    <div class="wp-card wp-card-pad wp-stack-tight wp-translation-reminder" role="status">
        <p class="wp-text-body"><strong>{{ __('platform.translation_sync.reminder_title') }}</strong></p>
        @if ($showServerPendingCount)
            <p class="wp-muted">{{ __('platform.translation_sync.reminder_body') }}</p>
            @if ($pendingCount > 0)
                <p class="wp-text-body">{{ __('platform.translation_sync.reminder_pending', ['count' => $pendingCount]) }}</p>
            @else
                <p class="wp-muted">{{ __('platform.translation_sync.reminder_none') }}</p>
            @endif
        @else
            <p class="wp-muted">{{ __('platform.translation_sync.reminder_local') }}</p>
        @endif
        <div>
            <a href="{{ route('platform.translations') }}" class="btn btn--ghost btn--sm">
                {{ __('platform.translation_sync.nav') }}
            </a>
        </div>
    </div>
@endif
