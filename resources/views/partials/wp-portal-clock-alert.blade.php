@if (($alert ?? null) instanceof \App\Enums\PortalRosterClockAlert)
    <span class="wp-portal-clock-alert" title="{{ __('time.portal.clock_alert.'.$alert->value) }}" aria-label="{{ __('time.portal.clock_alert.'.$alert->value) }}">
        <x-wp-icon name="alert-triangle" />
    </span>
@endif
