<p>{{ __('mail.verify_qr_report_email.intro', ['tenant' => $tenantName]) }}</p>

@if (filled($locationLine))
    <p>
        <strong>{{ __('mail.verify_qr_report_email.field_location') }}</strong><br>
        {{ $locationLine }}
        @if (filled($address))
            <br><span style="color: #64748b;">{{ $address }}</span>
        @endif
    </p>
@endif

@if (filled($reporterName))
    <p>
        <strong>{{ __('mail.verify_qr_report_email.field_reporter') }}</strong><br>
        {{ $reporterName }}
    </p>
@endif

@if (filled($description))
    <p>
        <strong>{{ __('mail.verify_qr_report_email.field_description') }}</strong><br>
        {{ $description }}
    </p>
@endif

@if ($photoCount > 0)
    <p>
        <strong>{{ __('mail.verify_qr_report_email.field_photos') }}</strong><br>
        {{ trans_choice('mail.verify_qr_report_email.photos_count', $photoCount, ['count' => $photoCount]) }}
    </p>
@endif

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
