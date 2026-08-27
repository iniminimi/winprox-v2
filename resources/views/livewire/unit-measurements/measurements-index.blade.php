<div class="wp-stack" data-manual-capture="unit-measurements-history">
    @include('partials.wp-unit-measurements-subnav')

    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="tasks"
                :title="__('unit_measurements.history.title')"
                help-page="unit-measurements.history"
                :subtitle="__('unit_measurements.history.subtitle')"
            />
        </div>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-cluster wp-cluster--between wp-cluster--wrap">
            <p class="wp-section-title">{{ __('unit_measurements.history.list_title') }}</p>
            <div class="wp-cluster wp-cluster--tight">
                <select class="wp-select wp-select--compact" wire:model.live="fieldFilter" aria-label="{{ __('unit_measurements.history.filters.field') }}">
                    <option value="">{{ __('unit_measurements.history.filters.all_fields') }}</option>
                    @foreach ($fields as $field)
                        <option value="{{ $field->id }}">{{ $field->name }}</option>
                    @endforeach
                </select>
                <select class="wp-select wp-select--compact" wire:model.live="locationFilter" aria-label="{{ __('unit_measurements.history.filters.location') }}">
                    <option value="">{{ __('unit_measurements.history.filters.all_locations') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name ?: $location->address }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="wp-list">
            @forelse ($measurements as $measurement)
                <div class="wp-card wp-card-pad wp-cluster wp-cluster--spread" wire:key="unit-measurement-{{ $measurement->id }}">
                    <div class="wp-stack-tight">
                        <div class="wp-cluster wp-cluster--wrap">
                            <strong>{{ $measurement->recorded_at?->format('d-m-Y H:i') }}</strong>
                            <span class="wp-pill wp-pill--progress">{{ $measurement->field?->name }}</span>
                        </div>
                        <p class="wp-text-body">
                            {{ $measurement->displayValue() }}
                            · {{ $measurement->location?->name }}
                            · {{ $measurement->unit?->name }}
                        </p>
                        <p class="wp-muted wp-text-sm">
                            {{ $measurement->worker?->displayName() ?? __('unit_measurements.history.reporter_unknown') }}
                            · {{ __('unit_measurements.sources.'.$measurement->source->value) }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('unit_measurements.history.empty') }}</p>
            @endforelse
        </div>

        {{ $measurements->links() }}
    </div>
</div>
