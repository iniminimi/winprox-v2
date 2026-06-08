<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('mail.welcome.html_title') }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
@php
    $logoUrl = url('/images/Winprox_logo_200.png');
@endphp
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="padding:16px 0;background:#f3f4f6;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;max-width:560px;background:#ffffff;border-radius:8px;padding:20px 22px;border:1px solid #d1d5db;">
                <tr>
                    <td style="font-size:16px;font-weight:600;color:#111827;line-height:1.4;">
                        {{ __('mail.welcome.heading') }}
                    </td>
                </tr>

                <tr>
                    <td style="padding-top:12px;font-size:14px;color:#1f2937;line-height:1.55;">
                        @if(filled($userName))
                            {{ __('mail.welcome.greeting_name', ['name' => $userName]) }}
                        @else
                            {{ __('mail.welcome.greeting') }}
                        @endif
                    </td>
                </tr>

                <tr>
                    <td style="padding-top:12px;font-size:14px;color:#1f2937;line-height:1.55;">
                        {{ __('mail.welcome.intro', ['tenant' => $tenantName]) }}
                    </td>
                </tr>

                <tr>
                    <td style="padding-top:12px;font-size:14px;color:#1f2937;line-height:1.55;">
                        {{ __('mail.welcome.account_details', ['email' => $userEmail, 'role' => $userRole]) }}
                    </td>
                </tr>

                <tr>
                    <td style="padding-top:12px;font-size:14px;color:#1f2937;line-height:1.55;">
                        {{ __('mail.welcome.admin_contact', ['name' => $adminName, 'email' => $adminEmail]) }}
                    </td>
                </tr>

                <tr>
                    <td style="padding-top:12px;font-size:14px;color:#1f2937;line-height:1.55;">
                        {{ __('mail.welcome.reset_hint', ['minutes' => $minutes]) }}
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:18px 0;">
                        <a href="{{ $resetUrl }}" style="background:#047857;color:#ffffff;text-decoration:none;padding:10px 20px;border-radius:6px;font-weight:600;font-size:14px;display:inline-block;">
                            {{ __('mail.welcome.set_password') }}
                        </a>
                    </td>
                </tr>

                <tr>
                    <td style="font-size:13px;color:#374151;text-align:center;line-height:1.55;">
                        {{ __('mail.welcome.link_fallback', ['action' => __('mail.welcome.set_password')]) }}<br>
                        <a href="{{ $resetUrl }}" style="color:#065f46;word-break:break-all;">{{ $resetUrl }}</a>
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:18px 0;">
                        <a href="{{ $loginUrl }}" style="background:#ffffff;color:#047857;border:2px solid #047857;text-decoration:none;padding:10px 20px;border-radius:6px;font-weight:600;font-size:14px;display:inline-block;">
                            {{ __('mail.welcome.open_platform') }}
                        </a>
                    </td>
                </tr>

                <tr>
                    <td style="padding-top:16px;font-size:13px;color:#1f2937;line-height:1.55;">
                        {{ __('mail.welcome.security_note') }}
                    </td>
                </tr>

                <tr>
                    <td style="padding-top:20px;text-align:center;font-size:12px;color:#4b5563;line-height:1.45;">
                        {{ __('mail.welcome.powered_by') }}
                    </td>
                </tr>

                <tr>
                    <td style="text-align:center;padding-top:8px;">
                        <img src="{{ $logoUrl }}" width="120" alt="WinProx" style="display:inline-block;">
                        <div style="padding-top:4px;font-size:12px;font-weight:500;color:#4b5563;line-height:1.2;">Work In Proximity</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
