<div class="wp-stack">
    <x-wp-page-head-title
        icon="document"
        :title="__('audit.page.title')"
        help-page="activity"
        :subtitle="__('audit.page.subtitle')"
    />

    <div class="wp-card wp-card-pad wp-stack">
        <label class="wp-label" for="activity-search">{{ __('audit.page.search') }}</label>
        <input id="activity-search" type="search" class="wp-input" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('audit.page.search_placeholder') }}" autocomplete="off">

        @if ($logs->isEmpty())
            <p class="wp-muted">{{ __('audit.page.empty') }}</p>
        @else
            <div class="wp-list wp-list--entity-rows">
                @foreach ($logs as $log)
                    @php
                        $summary = $summaries[$log->id] ?? null;
                    @endphp
                    <div class="wp-list-row" wire:key="audit-log-{{ $log->id }}">
                        <div class="wp-grow">
                            <p class="wp-text-body">
                                <strong>{{ $summary['title'] ?? $log->action }}</strong>
                                <span class="wp-muted wp-text-sm"> · {{ $summary['meta'] ?? '' }}</span>
                            </p>
                            @if (! empty($summary['context']))
                                <p class="wp-muted wp-text-sm">{{ $summary['context'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div>
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
