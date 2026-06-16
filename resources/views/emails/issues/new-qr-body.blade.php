<p>{{ __('mail.new_qr_issue.intro', ['tenant' => $tenantName]) }}</p>

@if(filled($locationLine))
    <p>
        <strong>{{ __('mail.new_qr_issue.field_location') }}</strong><br>
        {{ $locationLine }}
        @if(filled($address))
            <br><span style="color: #64748b;">{{ $address }}</span>
        @endif
    </p>
@endif

@if(filled($reporterName))
    <p>
        <strong>{{ __('mail.new_qr_issue.field_reporter') }}</strong><br>
        {{ $reporterName }}
    </p>
@endif

@if(filled($description))
    <p>
        <strong>{{ __('mail.new_qr_issue.field_description') }}</strong><br>
        {{ $description }}
    </p>
@endif

<p style="text-align: center; margin-top: 24px;">
    <a href="{{ $issueUrl }}" style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 14px;">
        {{ __('mail.new_qr_issue.open_issue') }}
    </a>
</p>

<p style="font-size: 13px; color: #64748b; text-align: center; margin-top: 16px;">
    {{ __('mail.new_qr_issue.link_fallback') }}<br>
    <a href="{{ $issueUrl }}" style="color: #059669; word-break: break-all;">{{ $issueUrl }}</a>
</p>
