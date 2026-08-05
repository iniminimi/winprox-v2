<div class="wp-stack" data-manual-capture="units-index">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="locations"
                :title="__('units.title')"
                :subtitle="__('units.subtitle')"
            />
        </div>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-cluster wp-cluster--between wp-cluster--wrap">
            <p class="wp-section-title">{{ __('units.list_title') }}</p>
            <div class="wp-cluster wp-cluster--tight">
                <label class="wp-field">
                    <span class="wp-label">{{ __('units.filters.location') }}</span>
                    <select class="wp-select wp-select--compact" wire:model.live="locationFilter" aria-label="{{ __('units.filters.location') }}">
                        <option value="">{{ __('units.filters.all_locations') }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name ?: $location->address }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>

        <div class="wp-list wp-list--entity-rows">
            @forelse ($units as $unit)
                <div class="wp-issue-row" wire:key="unit-{{ $unit->id }}">
                    <div class="wp-grow wp-stack-tight">
                        <a
                            href="{{ route('locations.show', ['location' => $unit->location_id, 'unit_id' => $unit->id]) }}"
                            class="wp-issue-row-link wp-stack-tight"
                        >
                            <p class="wp-issue-card-title">{{ $unit->localizedName() }}</p>
                            <p class="wp-issue-card-meta">{{ $unit->location?->name ?: $unit->location?->address }}</p>
                        </a>
                        <p class="wp-muted wp-text-sm">{{ $unit->category?->localizedName() ?? __('units.category_none') }}</p>
                    </div>

                    <div class="wp-cluster wp-cluster--tight">
                        <span class="wp-pill wp-pill--{{ $unit->is_active ? 'done' : 'closed' }}">
                            {{ $unit->is_active ? __('units.status.active') : __('units.status.inactive') }}
                        </span>
                        <span class="wp-pill wp-pill--{{ $unit->allowsUnitChecks() ? 'progress' : 'closed' }}">
                            {{ $unit->allowsUnitChecks() ? __('units.unit_checks.allowed') : __('units.unit_checks.disabled') }}
                        </span>
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('units.list.empty') }}</p>
            @endforelse
        </div>

        @if ($units->hasPages())
            <div class="wp-pagination">
                {{ $units->links() }}
            </div>
        @endif
    </div>
</div>

