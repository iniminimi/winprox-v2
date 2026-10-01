<p>{{ __('mail.verify_email.intro', ['tenant' => $tenantName]) }}</p>

<p style="text-align: center; margin-top: 24px;">
    <a href="{{ $verifyUrl }}" style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 14px;">
        {{ __('mail.verify_email.cta') }}
    </a>
</p>

<p style="font-size: 13px; color: #64748b; text-align: center; margin-top: 16px;">
    {{ __('mail.verify_email.link_fallback') }}<br>
    <a href="{{ $verifyUrl }}" style="color: #059669; word-break: break-all;">{{ $verifyUrl }}</a>
</p>

<p style="font-size: 13px; color: #64748b; margin-top: 16px;">
    {{ __('mail.verify_email.validity', ['minutes' => $minutes]) }}<br>
    {{ __('mail.verify_email.ignore') }}
</p>
