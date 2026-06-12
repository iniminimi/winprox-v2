<div class="wp-stack">
    <x-wp-page-head-title
        icon="document"
        :title="__('manual.hub.title')"
        :subtitle="__('manual.hub.subtitle')"
    />

    <div class="wp-stack">
        <div class="wp-stack-tight">
            <a href="{{ route('manual.general') }}" target="_blank" rel="noopener" class="btn btn--primary btn--block">
                {{ __('manual.hub.general') }}
            </a>
            <p class="wp-muted">{{ __('manual.hub.general_desc') }}</p>
        </div>

        <div class="wp-stack-tight">
            <a href="{{ route('manual.workers') }}" target="_blank" rel="noopener" class="btn btn--primary btn--block">
                {{ __('manual.hub.workers') }}
            </a>
            <p class="wp-muted">{{ __('manual.hub.workers_desc') }}</p>
        </div>

        <div class="wp-stack-tight">
            <a href="{{ route('manual.teamleaders') }}" target="_blank" rel="noopener" class="btn btn--primary btn--block">
                {{ __('manual.hub.teamleaders') }}
            </a>
            <p class="wp-muted">{{ __('manual.hub.teamleaders_desc') }}</p>
        </div>
    </div>
</div>
