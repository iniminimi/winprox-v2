<p>{{ __('mail.clock_point_qr.intro', ['tenant' => $tenantName]) }}</p>

@if(filled($locationLine))
    <p>{{ $locationLine }}</p>
@endif

<p><a href="{{ $portalUrl }}">{{ $portalUrl }}</a></p>

<p>{{ __('mail.clock_point_qr.closing') }}</p>
