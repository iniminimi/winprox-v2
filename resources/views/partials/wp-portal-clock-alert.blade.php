@if (($alert ?? null) instanceof \App\Enums\PortalRosterClockAlert)
    @php $alertLabel = __('time.portal.clock_alert.'.$alert->value); @endphp
    <span class="wp-portal-clock-alert" title="{{ $alertLabel }}" aria-label="{{ $alertLabel }}">
        <x-wp-icon name="alert-triangle" />
        @if ($showLabel ?? false)
            <span>{{ $alertLabel }}</span>
        @endif
    </span>
@endif
