{{ __('mail.welcome.heading') }}

@if(filled($userName))
{{ __('mail.welcome.greeting_name', ['name' => $userName]) }}
@else
{{ __('mail.welcome.greeting') }}
@endif

{{ __('mail.welcome.intro', ['tenant' => $tenantName]) }}

{{ __('mail.welcome.account_details', ['email' => $userEmail, 'role' => $userRole]) }}

{{ __('mail.welcome.admin_contact', ['name' => $adminName, 'email' => $adminEmail]) }}

{{ __('mail.welcome.reset_hint', ['minutes' => $minutes]) }}

{{ __('mail.welcome.set_password') }}: {{ $resetUrl }}

{{ __('mail.welcome.open_platform') }}: {{ $loginUrl }}

{{ __('mail.welcome.security_note') }}

{{ __('mail.welcome.powered_by') }}
WinProx - Work In Proximity
