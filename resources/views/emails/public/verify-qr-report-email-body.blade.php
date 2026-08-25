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

<p><strong>{{ __('mail.verify_qr_report_email.details_heading') }}</strong></p>

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

@if (filled($reporterEmail))
    <p>
        <strong>{{ __('mail.verify_qr_report_email.field_email') }}</strong><br>
        {{ $reporterEmail }}
    </p>
@endif

@if (filled($submittedAt))
    <p>
        <strong>{{ __('mail.verify_qr_report_email.field_submitted_at') }}</strong><br>
        {{ $submittedAt }}
    </p>
@endif

@if (filled($description))
    <p>
        <strong>{{ __('mail.verify_qr_report_email.field_description') }}</strong><br>
        {!! nl2br(e($description), false) !!}
    </p>
@endif

<p>
    <strong>{{ __('mail.verify_qr_report_email.field_photos') }}</strong><br>
    {{ $photoCount > 0
        ? trans_choice('mail.verify_qr_report_email.photos_count', $photoCount, ['count' => $photoCount])
        : __('mail.verify_qr_report_email.photos_none') }}
</p>

<p style="font-size: 13px; color: #64748b;">{{ __('mail.verify_qr_report_email.expiry', ['minutes' => $expiresInMinutes]) }}</p>
<p style="font-size: 13px; color: #64748b;">{{ __('mail.verify_qr_report_email.footer') }}</p>
