<div>
    @if (filled($guestName))
        <p>{{ __('mail.verify_qr_report_email.greeting', ['name' => $guestName]) }}</p>
    @endif
    <p>{{ __('mail.verify_qr_report_email.body', ['unit' => $unitName, 'location' => $locationName, 'minutes' => $expiresInMinutes]) }}</p>
    <p><a href="{{ $confirmUrl }}">{{ __('mail.verify_qr_report_email.cta') }}</a></p>
</div>
