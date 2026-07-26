<div>
    <p>{{ __('mail.reservation_confirm.greeting', ['name' => $guestName]) }}</p>
    @if ($alreadyConfirmed)
        <p>{{ __('mail.reservation_confirm.body_confirmed', ['unit' => $unitName, 'location' => $locationName, 'when' => $when]) }}</p>
        <p><a href="{{ $manageUrl }}">{{ __('mail.reservation_confirm.cta_manage') }}</a></p>
    @else
        <p>{{ __('mail.reservation_confirm.body_pending', ['unit' => $unitName, 'location' => $locationName, 'when' => $when, 'minutes' => $holdMinutes]) }}</p>
        <p><a href="{{ $confirmUrl }}">{{ __('mail.reservation_confirm.cta_confirm') }}</a></p>
        <p><a href="{{ $manageUrl }}">{{ __('mail.reservation_confirm.cta_manage') }}</a></p>
    @endif
</div>
