<div class="wp-stack" data-manual-capture="units-index">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                        icon="locations"
                        :title="__('units.title')"
                        help-page="units"
                        :subtitle="__('units.subtitle')"
                    />
        </div>
    </div>

    @if ($categories->isEmpty())
        <div class="wp-card wp-card-pad wp-onboarding-card">
            <div class="wp-stack">
                <p class="wp-text-body"><strong>{{ __('locations.onboarding.title_categories') }}</strong></p>
                <a href="{{ route('locations.index', ['section' => 'categories']) }}" class="btn btn--primary btn--sm wp-badge-critical">
                    {{ __('locations.onboarding.go_to_categories') }}
                </a>
            </div>
        </div>
    @elseif ($locations->isEmpty())
        <x-wp-onboarding-banner stage="locations" />
    @elseif ($units->isEmpty())
        <x-wp-onboarding-banner stage="units" />
    @endif

    @if ($units->isNotEmpty())
    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-filter-cell">
            <span class="wp-filter-inline-label">{{ __('units.filters.label') }}</span>
            <select id="units-location-filter" class="wp-select wp-select--compact" wire:model.live="locationFilter" aria-label="{{ __('units.filters.location') }}">
                <option value="">{{ __('units.filters.all_locations') }}</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}">{{ $location->name ?: $location->address }}</option>
                @endforeach
            </select>
            <select id="units-category-filter" class="wp-select wp-select--compact" wire:model.live="categoryFilter" aria-label="{{ __('units.filters.category') }}">
                <option value="">{{ __('units.filters.all_categories') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->localizedName() }}</option>
                @endforeach
            </select>
            <span class="wp-pill wp-pill--closed">{{ __('units.filters.count', ['count' => $units->total()]) }}</span>
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
                            <p class="wp-issue-card-meta">{{ __('units.row.meta', [
                                'location' => $unit->location?->name ?: $unit->location?->address ?: '—',
                                'category' => $unit->category?->localizedName() ?? __('units.category_none'),
                            ]) }}</p>
                        </a>
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
    @endif
</div>
