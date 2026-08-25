<p>{{ __('mail.verify_qr_report_email.intro', ['unit' => $unitName, 'location' => $locationName]) }}</p>

<p style="text-align: center; margin-top: 24px;">
    <a href="{{ $confirmUrl }}" style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 14px;">
        {{ __('mail.verify_qr_report_email.cta') }}
    </a>
</p>

<p style="font-size: 13px; color: #64748b; text-align: center; margin-top: 16px;">
    {{ __('mail.verify_qr_report_email.link_fallback') }}<br>
    <a href="{{ $confirmUrl }}" style="color: #059669; word-break: break-all;">{{ $confirmUrl }}</a>
</p>

<p style="font-size: 13px; color: #64748b;">{{ __('mail.verify_qr_report_email.expiry', ['minutes' => $expiresInMinutes]) }}</p>
<p style="font-size: 13px; color: #64748b;">{{ __('mail.verify_qr_report_email.footer') }}</p>
