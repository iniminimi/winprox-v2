<div class="wp-stack">
    <div class="wp-stack-tight">
        <h1 class="wp-page-title">{{ __('pages.locations.title') }}</h1>
        <p class="wp-muted">{{ __('pages.locations.subtitle') }}</p>
    </div>

    @forelse ($locations as $location)
        <div class="wp-card wp-card-pad wp-stack-tight" wire:key="loc-{{ $location->id }}">
            <div class="wp-row">
                <div class="wp-cluster">
                    <x-wp-icon name="locations" class="wp-icon" />
                    <h2 class="wp-section-title">{{ $location->name }}</h2>
                </div>
                <span class="wp-pill wp-pill--closed">{{ __('pages.locations.unit_count', ['count' => $location->units->count()]) }}</span>
            </div>

            @if ($location->address)
                <p class="wp-muted">{{ $location->address }}</p>
            @endif

            @if ($location->units->isNotEmpty())
                <div class="wp-chip-row">
                    @foreach ($location->units as $unit)
                        <span class="wp-chip" wire:key="unit-{{ $unit->id }}">
                            <x-wp-icon name="units" class="wp-icon" />
                            {{ $unit->name }}
                        </span>
                    @endforeach
                </div>
            @else
                <p class="wp-muted">{{ __('pages.locations.no_units') }}</p>
            @endif
        </div>
    @empty
        <div class="wp-card wp-card-pad wp-stub">
            <span class="wp-stub-icon"><x-wp-icon name="locations" /></span>
            <p class="wp-stub-text">{{ __('pages.locations.empty') }}</p>
        </div>
    @endforelse
</div>
