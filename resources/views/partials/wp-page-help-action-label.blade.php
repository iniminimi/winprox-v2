<p class="wp-page-help-item-label">
    @if (($labelIcon ?? null) === 'gps')
        @include('partials.wp-gps-pin-icon')
    @endif
    {{ $label }}
</p>
