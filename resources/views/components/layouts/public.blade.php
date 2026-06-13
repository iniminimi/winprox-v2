@props([
    'portalBgUrl' => null,
    'socialTitle' => null,
    'socialDescription' => null,
    'socialUrl' => null,
    'socialImage' => null,
    'ogContext' => 'portal',
])

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

    if ($portalBgUrl === null) {
        $portalBgUrl = $tenant ? $tenant->portalBackgroundPublicUrl() : null;
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      data-theme="{{ $portalTheme }}"
      @if ($applyTenantCustomTheme || $portalBgUrl)
          style="{{ $tenantCustomThemeVars }}{{ $portalBgUrl ? ' --wp-portal-bg: url(\'' . $portalBgUrl . '?v=' . now()->timestamp . '\');' : '' }}"
      @endif
      x-data="{
          tenantCustomTheme: @js($tenantCustomThemeVars !== '' ? $tenantCustomThemeVars : null),
          portalBgStyle: @js($portalBgUrl ? '--wp-portal-bg: url(\'' . $portalBgUrl . '?v=' . now()->timestamp . '\');' : null)
      }"
      x-on:ui-theme-changed.window="
          document.documentElement.dataset.theme = $event.detail.theme;
          let styles = [];
          if ($event.detail.theme === 'simple' && tenantCustomTheme) {
              styles.push(tenantCustomTheme);
          }
          if ($event.detail.theme !== 'dark' && portalBgStyle) {
              styles.push(portalBgStyle);
          }
          document.documentElement.style.cssText = styles.join(' ');
      ">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta http-equiv="Permissions-Policy" content="geolocation=(self)">
    <title>{{ $title ?? 'WinProx' }}</title>
    @include('partials.social-meta', [
        'title' => $socialTitle ?? __('portal.social.og_title'),
        'description' => $socialDescription ?? __('portal.social.og_description'),
        'url' => $socialUrl,
        'image' => $socialImage,
        'ogContext' => $ogContext,
    ])
    @include('partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="wp-shell {{ $bodyClass ?? 'wp-public-body' }}">
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