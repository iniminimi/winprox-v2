<div class="wp-stack" data-manual-capture="unit-checks">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="tasks"
                :title="__('unit_checks.title')"
                help-page="unit-checks"
                :subtitle="__('unit_checks.subtitle')"
            />
        </div>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-cluster wp-cluster--between wp-cluster--wrap">
            <p class="wp-section-title">{{ __('unit_checks.list_title') }}</p>
            <div class="wp-cluster wp-cluster--wrap">
                <select class="wp-select wp-select--compact" wire:model.live="resultFilter" aria-label="{{ __('unit_checks.filters.result') }}">
                    <option value="all">{{ __('unit_checks.filters.all_results') }}</option>
                    <option value="ok">{{ __('unit_checks.result.ok') }}</option>
                    <option value="not_ok">{{ __('unit_checks.result.not_ok') }}</option>
                </select>
                <select class="wp-select wp-select--compact" wire:model.live="locationFilter" aria-label="{{ __('unit_checks.filters.location') }}">
                    <option value="">{{ __('unit_checks.filters.all_locations') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name ?: $location->address }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="wp-list">
            @forelse ($checks as $check)
                <div class="wp-card wp-card-pad wp-cluster wp-cluster--spread" wire:key="unit-check-{{ $check->id }}">
                    <div class="wp-stack-tight">
                        <div class="wp-cluster wp-cluster--wrap">
                            <strong>{{ $check->checked_at?->format('d-m-Y H:i') }}</strong>
                            <span class="wp-pill wp-pill--{{ $check->result->pillVariant() }}">
                                {{ __('unit_checks.result.'.$check->result->value) }}
                            </span>
                        </div>
                        <p class="wp-text-body">
                            {{ $check->location?->name }}
                            ·
                            {{ $check->unit?->name }}
                        </p>
                        <p class="wp-muted wp-text-sm">
                            {{ $check->worker?->displayName() ?? __('unit_checks.worker_unknown') }}
                            @if ($check->team)
                                · {{ $check->team->localizedName() }}
                            @endif
                            @if (is_array($check->checklist_items) && $check->checklist_items !== [])
                                · {{ implode(', ', $check->checklist_items) }}
                            @endif
                            @if ($check->hasGps())
                                ·
                                <a href="{{ $check->googleMapsUrl() }}" target="_blank" rel="noopener noreferrer" class="wp-link">
                                    {{ __('unit_checks.gps_link') }}
                                </a>
                            @endif
                        </p>
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('unit_checks.empty') }}</p>
            @endforelse
        </div>

        @if ($checks->hasPages())
            <div class="wp-pagination">
                {{ $checks->links() }}
            </div>
        @endif
    </div>
</div>
