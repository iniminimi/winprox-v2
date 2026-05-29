<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="standard">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'WinProx' }}</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="wp-shell">
    <div class="wp-auth">
        <div class="wp-auth-card">
            {{ $slot }}
        </div>
    </div>

    @livewireScripts
</body>
</html>
