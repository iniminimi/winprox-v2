<p>{{ __('mail.clock_point_qr.intro', ['tenant' => $tenantName]) }}</p>

@if(filled($locationLine))
    <p>
        <strong>{{ __('mail.clock_point_qr.field_location') }}</strong><br>
        {{ $locationLine }}
    </p>
@endif

<p style="text-align: center; margin-top: 24px;">
    <a href="{{ $portalUrl }}" style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 14px;">
        {{ __('mail.clock_point_qr.cta') }}
    </a>
</p>

<p style="font-size: 13px; color: #64748b; text-align: center; margin-top: 16px;">
    {{ __('mail.clock_point_qr.link_fallback') }}<br>
    <a href="{{ $portalUrl }}" style="color: #059669; word-break: break-all;">{{ $portalUrl }}</a>
</p>
