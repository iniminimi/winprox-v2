{{ __('mail.outbound.greeting_name', ['name' => $recipientName]) }}

{{ __('mail.verify_email.intro', ['tenant' => $tenantName]) }}

{{ __('mail.verify_email.cta') }}:
{{ $verifyUrl }}

{{ __('mail.verify_email.validity', ['minutes' => $minutes]) }}
{{ __('mail.verify_email.ignore') }}
