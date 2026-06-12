<div class="wp-stack">
    <div class="wp-faq-wrap">
        <x-wp-page-head-title
            icon="document"
            :title="__('manual.hub.title')"
            :subtitle="__('manual.hub.subtitle')"
        />

        <ul class="wp-legal-index">
            <li class="wp-stack-tight">
                <a href="{{ route('manual.general') }}" target="_blank" rel="noopener" class="wp-legal-index-link">
                    {{ __('manual.hub.general') }}
                </a>
                <p class="wp-muted">{{ __('manual.hub.general_desc') }}</p>
            </li>

            <li class="wp-stack-tight">
                <a href="{{ route('manual.workers') }}" target="_blank" rel="noopener" class="wp-legal-index-link">
                    {{ __('manual.hub.workers') }}
                </a>
                <p class="wp-muted">{{ __('manual.hub.workers_desc') }}</p>
            </li>

            <li class="wp-stack-tight">
                <a href="{{ route('manual.teamleaders') }}" target="_blank" rel="noopener" class="wp-legal-index-link">
                    {{ __('manual.hub.teamleaders') }}
                </a>
                <p class="wp-muted">{{ __('manual.hub.teamleaders_desc') }}</p>
            </li>
        </ul>
    </div>
</div>
