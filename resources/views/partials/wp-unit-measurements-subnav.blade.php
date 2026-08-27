<nav class="wp-cluster wp-cluster--tight" aria-label="{{ __('unit_measurements.subnav.label') }}">
    <a href="{{ route('unit-measurements.fields.index') }}"
       class="btn btn--sm {{ request()->routeIs('unit-measurements.fields.*') ? 'btn--primary' : 'btn--ghost' }}">
        {{ __('unit_measurements.subnav.fields') }}
    </a>
    <a href="{{ route('unit-measurements.history') }}"
       class="btn btn--sm {{ request()->routeIs('unit-measurements.history') ? 'btn--primary' : 'btn--ghost' }}">
        {{ __('unit_measurements.subnav.history') }}
    </a>
</nav>
