@php
    $op = config('legal.operator', []);
    $name = $op['name'] ?? 'WinProx';
    $lines = $op['address_lines'] ?? [];
    $vat = trim((string) ($op['vat_label'] ?? ''));
    $kbo = trim((string) ($op['enterprise_number'] ?? ''));
    $email = trim((string) ($op['email'] ?? 'info@winprox.app'));
@endphp
<div class="wp-legal-contact">
    <p><strong>{{ $name }}</strong></p>
    @foreach ($lines as $line)
        <p>{{ $line }}</p>
    @endforeach
    @if ($vat !== '')
        <p>{{ __('legal.operator_vat', ['value' => $vat]) }}</p>
    @endif
    @if ($kbo !== '')
        <p>{{ __('legal.operator_enterprise', ['value' => $kbo]) }}</p>
    @endif
    <p>{{ __('legal.operator_email_label') }} <a href="mailto:{{ $email }}">{{ $email }}</a></p>
</div>
