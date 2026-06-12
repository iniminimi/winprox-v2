@props(['title' => 'WinProx'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="standard" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'WinProx' }}</title>
    @include('partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('manual-print-styles')
</head>
<body class="wp-shell">
    {{ $slot }}
    @stack('manual-print-footer')
    @livewireScripts
</body>
</html>
