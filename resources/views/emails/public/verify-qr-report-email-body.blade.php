<div>
<p>{{ __('mail.verify_qr_report_email.intro', ['unit' => $unitName, 'location' => $locationName]) }}</p>

@if (filled($description))
    <p>{{ $description }}</p>
@endif

<p><a href="{{ $confirmUrl }}">{{ __('mail.verify_qr_report_email.cta') }}</a></p>

<p style="font-size: 13px; color: #64748b;">{{ __('mail.verify_qr_report_email.link_fallback') }}<br>
    <a href="{{ $confirmUrl }}">{{ $confirmUrl }}</a>
</p>

<p style="font-size: 13px; color: #64748b;">{{ __('mail.verify_qr_report_email.expiry', ['minutes' => $expiresInMinutes]) }}</p>
<p style="font-size: 13px; color: #64748b;">{{ __('mail.verify_qr_report_email.footer') }}</p>
</div>
