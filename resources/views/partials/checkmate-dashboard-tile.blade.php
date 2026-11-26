@if ($kind === 'quick')
    <a href="{{ $tile['href'] }}"
       @class(['wp-kpi', 'wp-kpi--'.$tile['tone']])
       data-wp-intent-tile="{{ $tile['key'] }}"
       data-wp-intent-hideable="1"
       @if ($hidden) data-wp-intent-hidden="1" @endif
       draggable="false"
       wire:key="{{ $wireKey }}">
        <div class="wp-kpi-body">
            <span class="wp-kpi-icon" aria-hidden="true">
                <x-wp-icon :name="$tile['icon']" />
            </span>
            <div class="wp-kpi-main">
                <p class="wp-kpi-kicker">{{ __($tile['title']) }}</p>
                <p class="wp-kpi-meta wp-kpi-meta--wrap">{{ __($tile['body']) }}</p>
            </div>
        </div>
    </a>
@elseif (($tile['action'] ?? null) === 'open_clock_point_qr')
    <button
        type="button"
        wire:click="openCheckmateClockPointQr"
        @class([
            'wp-kpi',
            'wp-kpi--'.$tile['tone'],
            'wp-btn--prio-pulse' => $tile['pulse'] ?? false,
        ])
        data-wp-intent-tile="{{ $tile['key'] }}"
        data-wp-intent-hideable="1"
        @if ($hidden) data-wp-intent-hidden="1" @endif
        draggable="false"
        wire:key="{{ $wireKey }}"
    >
        <div class="wp-kpi-body">
            <span class="wp-kpi-icon" aria-hidden="true">
                <x-wp-icon :name="$tile['icon']" />
            </span>
            <div class="wp-kpi-main">
                <p class="wp-kpi-kicker">{{ __($tile['label']) }}</p>
                <p class="wp-kpi-meta wp-kpi-meta--wrap">{{ __($tile['body'] ?? '') }}</p>
            </div>
        </div>
    </button>
@else
    <a href="{{ $tile['href'] }}"
       @class(['wp-kpi', 'wp-kpi--'.$tile['tone']])
       data-wp-intent-tile="{{ $tile['key'] }}"
       data-wp-intent-hideable="1"
       @if ($hidden) data-wp-intent-hidden="1" @endif
       draggable="false"
       wire:key="{{ $wireKey }}">
        <div class="wp-kpi-body">
            <span class="wp-kpi-icon" aria-hidden="true">
                <x-wp-icon :name="$tile['icon']" />
            </span>
            <div class="wp-kpi-main">
                <p class="wp-kpi-kicker">{{ __($tile['label']) }}</p>
                <p class="wp-kpi-stats">
                    <span class="wp-kpi-value wp-tabular">{{ $tile['value'] ?? '' }}</span>
                    @if (! empty($tile['meta']))
                        <span class="wp-kpi-meta">{{ $tile['meta'] }}</span>
                    @endif
                </p>
            </div>
        </div>
    </a>
@endif
