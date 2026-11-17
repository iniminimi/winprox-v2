{{ __('mail.error_alert.intro', ['app' => $appUrl]) }}

{{ __('mail.error_alert.reference') }}: {{ $reference }}
{{ __('mail.error_alert.time') }}: {{ $occurredAt }} ({{ $timezone }})
{{ __('mail.error_alert.tenant') }}: {{ $context['tenant_id'] ?? '—' }}
{{ __('mail.error_alert.user') }}: {{ $context['userId'] ?? '—' }}
{{ __('mail.error_alert.request') }}: {{ isset($context['method']) ? $context['method'].' '.$context['url'] : __('mail.error_alert.background') }}
@isset($context['livewire_component'])
{{ __('mail.error_alert.component') }}: {{ $context['livewire_component'] }}
@endisset
{{ __('mail.error_alert.exception') }}: {{ $exceptionClass }}
{{ __('mail.error_alert.location') }}: {{ $location }}
@isset($appLocation)
{{ __('mail.error_alert.app_location') }}: {{ $appLocation }}
@endisset

{{ __('mail.error_alert.message') }}:
{!! $exceptionMessage !!}
@if ($repeated > 0)

{{ __('mail.error_alert.repeated', ['count' => $repeated]) }}
@endif

{{ __('mail.error_alert.log_hint') }}
