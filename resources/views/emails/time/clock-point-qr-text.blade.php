{{ __('mail.clock_point_qr.intro', ['tenant' => $tenantName]) }}

{{ __('mail.clock_point_qr.what', ['tenant' => $tenantName]) }}
@if(filled($locationLine))

{{ __('mail.clock_point_qr.location_label') }}: {{ $locationLine }}
@endif

{{ $portalUrl }}

{{ __('mail.clock_point_qr.note') }}

{{ __('mail.clock_point_qr.closing') }}
