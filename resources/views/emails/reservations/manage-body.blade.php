<div>
    <p>{{ __('mail.reservation_manage.greeting', ['name' => $guestName]) }}</p>
    <p>{{ __('mail.reservation_manage.body', ['unit' => $unitName, 'location' => $locationName, 'when' => $when]) }}</p>
    <p><a href="{{ $manageUrl }}">{{ __('mail.reservation_manage.cta') }}</a></p>
</div>
