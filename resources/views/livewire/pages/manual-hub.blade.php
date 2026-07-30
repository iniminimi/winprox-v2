<div class="wp-stack">
    <div class="wp-faq-wrap wp-stack">
        <x-wp-page-head-title
            icon="document"
            :title="__('manual.hub.title')"
            :subtitle="__('manual.hub.subtitle')"
        />

        <div class="wp-stack">
            <div class="wp-stack-tight">
                <a href="{{ route('manual.general') }}" target="_blank" rel="noopener" class="btn btn--primary btn--sm">
                    {{ __('manual.hub.general') }}
                </a>
                <p class="wp-muted">{{ __('manual.hub.general_desc') }}</p>
            </div>

            <div class="wp-stack-tight">
                <a href="{{ route('manual.workers') }}" target="_blank" rel="noopener" class="btn btn--primary btn--sm">
                    {{ __('manual.hub.workers') }}
                </a>
                <p class="wp-muted">{{ __('manual.hub.workers_desc') }}</p>
            </div>

            <div class="wp-stack-tight">
                <a href="{{ route('manual.teamleaders') }}" target="_blank" rel="noopener" class="btn btn--primary btn--sm">
                    {{ __('manual.hub.teamleaders') }}
                </a>
                <p class="wp-muted">{{ __('manual.hub.teamleaders_desc') }}</p>
            </div>

            <div class="wp-stack-tight">
                <a href="{{ route('product.features') }}" target="_blank" rel="noopener" class="btn btn--ghost btn--sm">
                    {{ __('manual.hub.features_overview') }}
                </a>
                <p class="wp-muted">{{ __('manual.hub.features_overview_desc') }}</p>
            </div>

            <div class="wp-stack-tight">
                <a href="{{ route('product.technical') }}" target="_blank" rel="noopener" class="btn btn--ghost btn--sm">
                    {{ __('manual.hub.technical_sheet') }}
                </a>
                <p class="wp-muted">{{ __('manual.hub.technical_sheet_desc') }}</p>
            </div>
        </div>
    </div>
</div>
