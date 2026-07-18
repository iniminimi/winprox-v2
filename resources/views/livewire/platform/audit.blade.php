<div class="wp-stack">
    <x-wp-page-head-title
        icon="document"
        :title="__('platform.audit.title')"
        help-page="platform.audit"
        :subtitle="__('platform.audit.subtitle')"
    />

    <div class="wp-card wp-card-pad wp-stack">
        <label class="wp-label" for="platform-audit-search">{{ __('platform.audit.search') }}</label>
        <input id="platform-audit-search" type="search" class="wp-input" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('platform.audit.search_placeholder') }}" autocomplete="off">

        @if ($logs->isEmpty())
            <p class="wp-muted">{{ __('platform.audit.empty') }}</p>
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
