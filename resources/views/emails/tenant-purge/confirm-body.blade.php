<p>{{ __('mail.tenant_purge.confirm.intro', ['tenant' => $tenantName]) }}</p>

@if ($track === 'paid')
    <p>{{ __('mail.tenant_purge.confirm.paid_note') }}</p>
@else
    <p>{{ __('mail.tenant_purge.confirm.trial_note') }}</p>
@endif

<p style="text-align: center; margin-top: 24px;">
    <a href="{{ $confirmUrl }}" style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 14px;">
        {{ __('mail.tenant_purge.confirm.cta_button') }}
    </a>
</p>

<p style="font-size: 13px; color: #64748b; text-align: center; margin-top: 16px;">
    {{ __('mail.tenant_purge.link_fallback') }}<br>
    <a href="{{ $confirmUrl }}" style="color: #059669; word-break: break-all;">{{ $confirmUrl }}</a>
</p>

<p>{{ __('mail.tenant_purge.confirm.expiry', ['hours' => $hours]) }}</p>
<p style="font-size: 13px; color: #64748b;">{{ __('mail.tenant_purge.confirm.footer') }}</p>
