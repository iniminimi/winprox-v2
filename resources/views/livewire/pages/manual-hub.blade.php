<div class="wp-stack">
    <div class="wp-faq-wrap wp-stack">
        <x-wp-page-head-title
            :assistant-video="asset('video/assistant_legal_80.mp4')"
            assistant-video-loop
            :title="__('manual.hub.title')"
            :subtitle="__('manual.hub.subtitle')"
        />

        <div class="wp-card wp-card-pad">
            <div class="wp-list wp-list--entity-rows">
                <a href="{{ route('manual.general') }}" target="_blank" rel="noopener" class="wp-issue-row">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-card-title">{{ __('manual.hub.general') }}</p>
                        <p class="wp-issue-card-desc">{{ __('manual.hub.general_desc') }}</p>
                    </div>
                </a>
                <a href="{{ route('manual.workers') }}" target="_blank" rel="noopener" class="wp-issue-row">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-card-title">{{ __('manual.hub.workers') }}</p>
                        <p class="wp-issue-card-desc">{{ __('manual.hub.workers_desc') }}</p>
                    </div>
                </a>
                <a href="{{ route('manual.teamleaders') }}" target="_blank" rel="noopener" class="wp-issue-row">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-card-title">{{ __('manual.hub.teamleaders') }}</p>
                        <p class="wp-issue-card-desc">{{ __('manual.hub.teamleaders_desc') }}</p>
                    </div>
                </a>
                <a href="{{ route('product.features') }}" target="_blank" rel="noopener" class="wp-issue-row">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-card-title">{{ __('manual.hub.features_overview') }}</p>
                        <p class="wp-issue-card-desc">{{ __('manual.hub.features_overview_desc') }}</p>
                    </div>
                </a>
                <a href="{{ route('product.technical') }}" target="_blank" rel="noopener" class="wp-issue-row">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-card-title">{{ __('manual.hub.technical_sheet') }}</p>
                        <p class="wp-issue-card-desc">{{ __('manual.hub.technical_sheet_desc') }}</p>
                    </div>
                </a>
                <a href="{{ route('product.api_webhooks') }}" target="_blank" rel="noopener" class="wp-issue-row">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-card-title">{{ __('manual.hub.api_webhooks') }}</p>
                        <p class="wp-issue-card-desc">{{ __('manual.hub.api_webhooks_desc') }}</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
