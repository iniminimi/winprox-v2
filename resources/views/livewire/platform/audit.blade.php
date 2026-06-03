<div class="wp-stack">
    <x-wp-page-head-title
        icon="document"
        :title="__('platform.audit.title')"
        help-page="platform.audit"
        :subtitle="__('platform.audit.subtitle')"
    />

    <div class="wp-card wp-card-pad wp-stack">
        <label class="wp-label" for="platform-audit-search">{{ __('platform.search') }}</label>
        <input id="platform-audit-search" type="search" class="wp-input" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('platform.audit.search_placeholder') }}" autocomplete="off">

        @if ($logs->isEmpty())
            <p class="wp-muted">{{ __('platform.audit.empty') }}</p>
        @else
            <ul class="wp-list-plain wp-stack-tight">
                @foreach ($logs as $log)
                    <li class="wp-list-row" wire:key="audit-log-{{ $log->id }}">
                        <div class="wp-grow">
                            <p class="wp-text-body"><strong>{{ $log->action }}</strong></p>
                            <p class="wp-muted wp-text-sm">
                                {{ $log->tenant?->name ?? '—' }} · {{ $log->user?->email ?? '—' }} · {{ $log->created_at?->format('d-m-Y H:i') }}
                            </p>
                            @if ($log->model_type || $log->model_id)
                                <p class="wp-muted wp-text-sm">{{ $log->model_type ?? '—' }} #{{ $log->model_id ?? '—' }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
