<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="standard">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $location->name }} — WinProx</title>
    @vite(['resources/css/app.css'])
</head>
<body class="wp-shell">
    <main class="wp-main wp-stack">
        <h1 class="wp-page-title">{{ $location->name }}</h1>
        <p class="wp-muted">{{ __('locations.location_qr_scan_hint') }}</p>
    </main>
</body>
</html>
