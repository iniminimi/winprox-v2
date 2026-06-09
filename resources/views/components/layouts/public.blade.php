<!DOCTYPE html>
@php
    use App\Enums\UiTheme;
    use App\Support\Ui\UiThemeResolver;

    $tenant = \App\Support\Tenancy::id() ? \App\Models\Tenant::find(\App\Support\Tenancy::id()) : null;
    $portalTheme = $uiTheme ?? UiThemeResolver::resolvePortal();
    $tenantCustomThemeVars = '';

    if ($tenant && $tenant->custom_theme_active) {
        $bg = $tenant->custom_theme_bg ?? '#e7e8ec';
        $btn = $tenant->custom_theme_btn ?? '#059669';
        $tenantCustomThemeVars = "--wp-bg: {$bg}; --wp-accent: {$btn}; --wp-accent-strong: {$btn};";
    }

    $applyTenantCustomTheme = $tenantCustomThemeVars !== ''
        && UiTheme::tryFromString($portalTheme) === UiTheme::Simple;

    $portalBgUrl = $tenant ? $tenant->portalBackgroundPublicUrl() : null;
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      data-theme="{{ $portalTheme }}"
      @if ($applyTenantCustomTheme) style="{{ $tenantCustomThemeVars }}" @endif
      x-data="{ tenantCustomTheme: @js($tenantCustomThemeVars !== '' ? $tenantCustomThemeVars : null) }"
      x-on:ui-theme-changed.window="
        document.documentElement.dataset.theme = $event.detail.theme;
        if ($event.detail.theme === 'simple' && tenantCustomTheme) {
            document.documentElement.style.cssText = tenantCustomTheme;
        } else {
            document.documentElement.style.cssText = '';
        }
      ">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'WinProx' }}</title>
    @include('partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="wp-shell {{ $bodyClass ?? 'wp-public-body' }}"
      @if ($portalBgUrl) style="--wp-portal-bg: url('{{ $portalBgUrl }}');" @endif>
    <main class="wp-portal">
        {{ $slot }}
        
        <div class="wp-portal-footer">
            <span class="wp-chip">Powered by WinProx.app</span>
        </div>
    </main>

    @livewireScripts
    <script>
        window.__translations = window.__translations || {};
        window.__translations['portal.unit.upload_failed_offline'] = @json(__('portal.unit.upload_failed_offline'));
    </script>
</body>
</html>